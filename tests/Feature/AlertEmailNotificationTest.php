<?php

use App\Jobs\SendAlertEmailNotifications;
use App\Mail\AlertNotificationMail;
use App\Models\Admin\AlertType;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Operator\Operator;
use App\Models\Recipient\Recipient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

// ============================================================
// Operator store — job dispatched when email channel selected
// ============================================================

test('storing an alert with email channel dispatches SendAlertEmailNotifications job', function () {
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
            'channels' => ['email'],
        ])
        ->assertStatus(201);

    Queue::assertPushed(SendAlertEmailNotifications::class, function ($job) {
        return $job->alert->title === 'Test Emergency';
    });
});

test('storing an alert without email channel does not dispatch SendAlertEmailNotifications job', function () {
    Queue::fake();

    $operator = Operator::factory()->active()->create();
    $alertType = AlertType::factory()->create();

    $this->actingAs($operator, 'operator')
        ->postJson('/api/operator/alerts', [
            'alert_type_id' => $alertType->id,
            'title' => 'In-App Only',
            'message' => 'No email.',
            'severity' => 'low',
            'target_roles' => ['staff'],
            'channels' => ['web_push'],
        ])
        ->assertStatus(201);

    Queue::assertNotPushed(SendAlertEmailNotifications::class);
});

// ============================================================
// SendAlertEmailNotifications job — queues mail per recipient
// ============================================================

test('SendAlertEmailNotifications queues one mail per targeted recipient', function () {
    Mail::fake();

    $alert = Alert::factory()->forRoles(['student'])->withEmail()->create();

    Recipient::factory()->student()->count(3)->create();
    Recipient::factory()->faculty()->count(2)->create(); // should not receive

    (new SendAlertEmailNotifications($alert))->handle();

    Mail::assertQueued(AlertNotificationMail::class, 3);
});

test('SendAlertEmailNotifications queues mail only to recipients matching target roles', function () {
    Mail::fake();

    $alert = Alert::factory()->forRoles(['faculty', 'staff'])->withEmail()->create();

    Recipient::factory()->faculty()->count(2)->create();
    Recipient::factory()->staff()->count(1)->create();
    Recipient::factory()->student()->count(4)->create(); // not targeted

    (new SendAlertEmailNotifications($alert))->handle();

    Mail::assertQueued(AlertNotificationMail::class, 3);
});

// ============================================================
// Email acknowledge endpoint — signed URL marks alert as read
// ============================================================

test('valid signed acknowledge URL marks alert as read and returns confirmation page', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $url = URL::signedRoute(
        'recipient.alerts.acknowledge-email',
        ['alert' => $alert->id, 'recipient' => $recipient->id],
        now()->addDays(7),
    );

    $this->get($url)->assertOk()->assertViewIs('emails.alert-acknowledged');

    $this->assertDatabaseHas('alert_recipient_reads', [
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'acknowledged_via' => 'email',
    ]);
});

test('acknowledging the same alert twice via email is idempotent', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $url = URL::signedRoute(
        'recipient.alerts.acknowledge-email',
        ['alert' => $alert->id, 'recipient' => $recipient->id],
        now()->addDays(7),
    );

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    expect(
        AlertRecipientRead::where('alert_id', $alert->id)
            ->where('recipient_id', $recipient->id)
            ->count()
    )->toBe(1);
});

test('tampered signed acknowledge URL returns 403', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $url = URL::signedRoute(
        'recipient.alerts.acknowledge-email',
        ['alert' => $alert->id, 'recipient' => $recipient->id],
        now()->addDays(7),
    );

    $tamperedUrl = $url.'&tampered=1';

    $this->get($tamperedUrl)->assertForbidden();

    $this->assertDatabaseMissing('alert_recipient_reads', [
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
    ]);
});

test('expired signed acknowledge URL returns 403', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $url = URL::signedRoute(
        'recipient.alerts.acknowledge-email',
        ['alert' => $alert->id, 'recipient' => $recipient->id],
        now()->subMinute(), // already expired
    );

    $this->get($url)->assertForbidden();
});

// ============================================================
// acknowledged_via — channel recorded correctly per method
// ============================================================

test('markRead stores acknowledged_via as in-app', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $this->actingAs($recipient, 'recipient')
        ->patchJson("/api/recipient/alerts/{$alert->id}/read")
        ->assertNoContent();

    $this->assertDatabaseHas('alert_recipient_reads', [
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'acknowledged_via' => 'in-app',
    ]);
});

test('index response includes acknowledged_via for read alerts', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    AlertRecipientRead::create([
        'alert_id' => $alert->id,
        'recipient_id' => $recipient->id,
        'read_at' => now(),
        'acknowledged_via' => 'email',
    ]);

    $response = $this->actingAs($recipient, 'recipient')
        ->getJson('/api/recipient/alerts')
        ->assertOk();

    $found = collect($response->json('data'))->firstWhere('id', $alert->id);

    expect($found['is_read'])->toBeTrue();
    expect($found['acknowledged_via'])->toBe('email');
});

test('index response returns null acknowledged_via for unread alerts', function () {
    $alert = Alert::factory()->forRoles(['student'])->create();
    $recipient = Recipient::factory()->student()->create();

    $response = $this->actingAs($recipient, 'recipient')
        ->getJson('/api/recipient/alerts')
        ->assertOk();

    $found = collect($response->json('data'))->firstWhere('id', $alert->id);

    expect($found['is_read'])->toBeFalse();
    expect($found['acknowledged_via'])->toBeNull();
});
