<?php

use App\Jobs\SendAlertSmsNotifications;
use App\Models\Admin\AlertType;
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

    Queue::assertPushed(SendAlertSmsNotifications::class, function ($job) {
        return $job->alert->title === 'Test Emergency';
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

test('SendAlertSmsNotifications sends one sms per targeted recipient', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();

    Recipient::factory()->student()->count(3)->create();
    Recipient::factory()->faculty()->count(2)->create();

    (new SendAlertSmsNotifications($alert))->handle(app(PhilSmsService::class));

    Http::assertSentCount(3);
});

test('SendAlertSmsNotifications sends sms only to recipients matching target roles', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['faculty', 'staff'])->withSms()->create();

    Recipient::factory()->faculty()->count(2)->create();
    Recipient::factory()->staff()->count(1)->create();
    Recipient::factory()->student()->count(4)->create();

    (new SendAlertSmsNotifications($alert))->handle(app(PhilSmsService::class));

    Http::assertSentCount(3);
});

test('SendAlertSmsNotifications skips recipients with invalid contact numbers', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();

    Recipient::factory()->student()->create(['contact_number' => '09171234567']);
    Recipient::factory()->student()->create(['contact_number' => 'invalid']);

    (new SendAlertSmsNotifications($alert))->handle(app(PhilSmsService::class));

    Http::assertSentCount(1);
});

test('SendAlertSmsNotifications includes signed acknowledge link in sms payload', function () {
    Http::fake([
        'app.philsms.com/*' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    $alert = Alert::factory()->forRoles(['student'])->withSms()->create();
    Recipient::factory()->student()->create(['contact_number' => '09171234567']);

    (new SendAlertSmsNotifications($alert))->handle(app(PhilSmsService::class));

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return str_contains($payload['message'] ?? '', 'Acknowledge:')
            && str_contains($payload['message'] ?? '', 'acknowledge-sms')
            && ($payload['recipient'] ?? null) === '639171234567'
            && ($payload['sender_id'] ?? null) === 'WBEAMS'
            && ($payload['type'] ?? null) === 'plain';
    });
});

test('valid signed acknowledge sms url marks alert as read and returns confirmation page', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

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
