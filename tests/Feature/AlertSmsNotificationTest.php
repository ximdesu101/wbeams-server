<?php

use App\Jobs\SendAlertSmsNotifications;
use App\Models\Admin\AlertType;
use App\Models\NotificationLog;
use App\Models\Operator\Alert;
use App\Models\Operator\Operator;
use App\Models\Recipient\Recipient;
use App\Services\PhilSmsService;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config([
        'services.philsms.api_token' => 'test-api-token',
        'services.philsms.sender_id' => 'WBEAMS',
        'services.philsms.endpoint' => 'https://app.philsms.com/api/v3/sms/send',
    ]);
});

test('storing an alert with sms channel dispatches SendAlertSmsNotifications job', function () {
    Queue::fake();

    $operator = Operator::factory()->active()->create();
    $alertType = AlertType::factory()->create();
    $recipient = Recipient::factory()->student()->create();

    $this->actingAs($operator, 'operator')
        ->postJson('/api/operator/alerts', [
            'alert_type_id' => $alertType->id,
            'title' => 'Test Emergency',
            'message' => 'Stay safe.',
            'severity' => 'high',
            'target_roles' => ['student'],
            'channels' => ['sms'],
        ])
        ->assertStatus(201);

    Queue::assertPushed(SendAlertSmsNotifications::class, function ($job) use ($recipient) {
        return NotificationLog::find($job->notificationLogId)?->recipient_id === $recipient->id;
    });
});

test('storing an alert without sms channel does not dispatch SendAlertSmsNotifications job', function () {
    Queue::fake();

    $operator = Operator::factory()->active()->create();
    $alertType = AlertType::factory()->create();

    $this->actingAs($operator, 'operator')
        ->postJson('/api/operator/alerts', [
            'alert_type_id' => $alertType->id,
            'title' => 'In-App Only',
            'message' => 'No sms.',
            'severity' => 'low',
            'target_roles' => ['staff'],
            'channels' => ['web_push'],
        ])
        ->assertStatus(201);

    Queue::assertNotPushed(SendAlertSmsNotifications::class);
});

test('legacy alert-level sms jobs fan out into tracked recipient deliveries', function () {
    Queue::fake();

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();
    $recipient = Recipient::factory()->student()->create();

    (new SendAlertSmsNotifications($alert))->handle(app(PhilSmsService::class));

    $delivery = NotificationLog::where('alert_id', $alert->id)->firstOrFail();
    expect($delivery->recipient_id)->toBe($recipient->id);
    Queue::assertPushed(SendAlertSmsNotifications::class, function ($job) use ($delivery) {
        return $job->notificationLogId === $delivery->id;
    });
});

test('SendAlertSmsNotifications sends one sms per targeted recipient', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();

    $recipients = Recipient::factory()->student()->count(3)->create();
    Recipient::factory()->faculty()->count(2)->create();

    foreach ($recipients as $recipient) {
        $delivery = NotificationLog::create([
            'alert_id' => $alert->id,
            'recipient_id' => $recipient->id,
            'channel' => 'sms',
        ]);

        (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));
        (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));
    }

    Http::assertSentCount(3);
    expect(NotificationLog::where('channel', 'sms')->where('status', 'sent')->count())->toBe(3);
});

test('SendAlertSmsNotifications sends sms only to recipients matching target roles', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['faculty', 'staff'])->withSms()->create();

    $recipients = Recipient::factory()->faculty()->count(2)->create()
        ->merge(Recipient::factory()->staff()->count(1)->create());
    Recipient::factory()->student()->count(4)->create();

    foreach ($recipients as $recipient) {
        $delivery = NotificationLog::create([
            'alert_id' => $alert->id,
            'recipient_id' => $recipient->id,
            'channel' => 'sms',
        ]);

        (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));
    }

    Http::assertSentCount(3);
});

test('SendAlertSmsNotifications skips recipients with invalid contact numbers', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();

    $recipients = Recipient::factory()->student()->count(2)->create();
    $recipients[0]->update(['contact_number' => '09171234567']);
    $recipients[1]->update(['contact_number' => 'invalid']);

    foreach ($recipients as $recipient) {
        $delivery = NotificationLog::create([
            'alert_id' => $alert->id,
            'recipient_id' => $recipient->id,
            'channel' => 'sms',
        ]);

        (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));
    }

    Http::assertSentCount(1);
    expect(NotificationLog::where('status', 'failed')->count())->toBe(1);
});

test('SendAlertSmsNotifications includes signed acknowledge link in sms payload', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();
    $recipient = Recipient::factory()->student()->create(['contact_number' => '09171234567']);
    $delivery = NotificationLog::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
    ]);

    (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return str_contains($payload['message'] ?? '', 'Acknowledge:')
            && str_contains($payload['message'] ?? '', 'acknowledge-sms')
            && ($payload['recipient'] ?? null) === '+639171234567'
            && ($payload['sender_id'] ?? null) === 'WBEAMS'
            && ($payload['type'] ?? null) === 'plain';
    });
});

test('valid signed acknowledge sms url marks alert as read and returns confirmation page', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();
    NotificationLog::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $url = URL::signedRoute(
        'recipient.alerts.acknowledge-sms',
        ['alert' => $alert->id, 'recipient' => $recipient->id],
        now()->addDays(7),
    );

    $this->get($url)->assertOk()->assertViewIs('emails.alert-acknowledged');

    $this->assertDatabaseHas('alert_recipient_reads', [
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'acknowledged_via' => 'sms',
    ]);
    $this->assertDatabaseHas('notification_logs', [
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
        'status' => NotificationLog::STATUS_DELIVERED,
    ]);

    $delivery = NotificationLog::where('alert_id', $alert->id)
        ->where('recipient_id', $recipient->id)
        ->where('channel', 'sms')
        ->firstOrFail();
    (new SendAlertSmsNotifications($delivery->id))->failed(new RuntimeException('Late retry callback'));

    $this->assertDatabaseHas('notification_logs', [
        'id' => $delivery->id,
        'status' => NotificationLog::STATUS_DELIVERED,
    ]);
});

test('temporary sms provider failures stay queued until the job exhausts retries', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'error'], 503),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();
    $recipient = Recipient::factory()->student()->create();
    $delivery = NotificationLog::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
    ]);
    $job = new SendAlertSmsNotifications($delivery->id);

    expect(fn () => $job->handle(app(PhilSmsService::class)))->toThrow(RuntimeException::class);
    $this->assertDatabaseHas('notification_logs', [
        'id' => $delivery->id,
        'status' => NotificationLog::STATUS_QUEUED,
        'failed_at' => null,
    ]);

    $job->failed(new RuntimeException('Retries exhausted'));

    $this->assertDatabaseHas('notification_logs', [
        'id' => $delivery->id,
        'status' => NotificationLog::STATUS_FAILED,
    ]);
});

test('sms deliveries left processing past retry_after can be resumed', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();
    $recipient = Recipient::factory()->student()->create();
    $delivery = NotificationLog::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'channel' => 'sms',
        'status' => NotificationLog::STATUS_PROCESSING,
        'updated_at' => now()->subSeconds(120),
    ]);
    NotificationLog::query()
        ->whereKey($delivery->id)
        ->update(['updated_at' => now()->subSeconds(120)]);

    (new SendAlertSmsNotifications($delivery->id))->handle(app(PhilSmsService::class));

    Http::assertSentCount(1);
    $this->assertDatabaseHas('notification_logs', [
        'id' => $delivery->id,
        'status' => NotificationLog::STATUS_SENT,
    ]);
});

test('philippine phone numbers are normalized for philsms', function () {
    expect(PhilippinePhoneNumber::normalize('09171234567'))->toBe('639171234567');
    expect(PhilippinePhoneNumber::normalize('+63 917 123 4567'))->toBe('639171234567');
    expect(PhilippinePhoneNumber::normalize('639171234567'))->toBe('639171234567');
    expect(PhilippinePhoneNumber::normalize('invalid'))->toBeNull();
    expect(PhilippinePhoneNumber::normalize(null))->toBeNull();
});

test('PhilSmsService throws when philsms is not configured', function () {
    config(['services.philsms.api_token' => null]);

    $service = app(PhilSmsService::class);

    expect(fn () => $service->send('639171234567', 'Test message'))
        ->toThrow(RuntimeException::class, 'PhilSMS is not configured');
});
