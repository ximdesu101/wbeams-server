<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Models\NotificationLog;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Recipient\Recipient;
use Illuminate\Support\Facades\DB;

test('email metrics distinguish delivery outcomes from live queue states', function () {
    config(['queue.default' => 'database']);

    $alert = Alert::factory()->forRoles(['student'])->withEmail()->create();
    $recipients = Recipient::factory()->student()->count(4)->create();

    foreach ([
        [NotificationLog::STATUS_SENT, $recipients[0]],
        [NotificationLog::STATUS_DELIVERED, $recipients[1]],
        [NotificationLog::STATUS_FAILED, $recipients[2]],
        [NotificationLog::STATUS_QUEUED, $recipients[3]],
    ] as [$status, $recipient]) {
        NotificationLog::create([
            'alert_id' => $alert->id,
            'recipient_id' => $recipient->id,
            'channel' => 'email',
            'status' => $status,
            'sent_at' => in_array($status, [NotificationLog::STATUS_SENT, NotificationLog::STATUS_DELIVERED], true)
                ? now()
                : null,
            'delivered_at' => $status === NotificationLog::STATUS_DELIVERED ? now() : null,
            'failed_at' => $status === NotificationLog::STATUS_FAILED ? now() : null,
        ]);
    }

    $queueConfig = config('queue.connections.database');
    $queueDatabase = DB::connection($queueConfig['connection'] ?: config('database.default'));
    $queue = $queueConfig['queue'] ?? 'default';
    $payloadFor = fn (string $job) => json_encode(['displayName' => $job], JSON_THROW_ON_ERROR);

    $queueDatabase->table($queueConfig['table'] ?? 'jobs')->insert([
        [
            'queue' => $queue,
            'payload' => $payloadFor('App\\Jobs\\SendAlertEmailNotifications'),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => $queue,
            'payload' => $payloadFor('App\\Jobs\\SendAlertEmailNotifications'),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => $queue,
            'payload' => $payloadFor('App\\Jobs\\SendAlertSmsNotifications'),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => $queue,
            'payload' => $payloadFor('App\\Mail\\AlertNotificationMail'),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $failedConfig = config('queue.failed');
    DB::connection($failedConfig['database'] ?? config('database.default'))
        ->table($failedConfig['table'] ?? 'failed_jobs')
        ->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => $payloadFor('App\\Jobs\\SendAlertEmailNotifications'),
            'exception' => 'Final queue failure',
            'failed_at' => now(),
        ]);

    $metrics = app(AnalyticsController::class)->emailMetrics()->getData(true)['data'];
    $chart = app(AnalyticsController::class)->emailDeliveryPerformance()->getData(true)['data'];

    expect($metrics['sent'])->toBe(2)
        ->and($metrics['delivered'])->toBe(1)
        ->and($metrics['failed'])->toBe(1)
        ->and($metrics['pending'])->toBe(1)
        ->and($metrics['jobs'])->toBe(2)
        ->and($metrics['processing_jobs'])->toBe(1)
        ->and($metrics['failed_jobs'])->toBe(1)
        ->and($metrics['failure_rate'])->toBe(33.3);

    expect($chart)->toHaveCount(12)
        ->and($chart[9])->toMatchArray([
            'month' => 'October',
            'sent' => 2,
            'delivered' => 1,
            'failed' => 1,
        ]);
});

test('sms delivery performance returns the chart data shape with no records', function () {
    $chart = app(AnalyticsController::class)->smsDeliveryPerformance()->getData(true)['data'];

    expect($chart)->toHaveCount(12)
        ->and($chart[0])->toMatchArray([
            'month' => 'January',
            'sent' => 0,
            'delivered' => 0,
            'failed' => 0,
        ]);
});

test('backfill imports only confirmed deliveries whose alert used that channel', function () {
    $emailAlert = Alert::factory()->forRoles(['student'])->withEmail()->create();
    $smsAlert = Alert::factory()->forRoles(['student'])->withSms()->create();
    $recipient = Recipient::factory()->student()->create();

    AlertRecipientRead::create([
        'alert_id' => $emailAlert->id,
        'recipient_id' => $recipient->id,
        'read_at' => now()->subDay(),
        'acknowledged_via' => 'email',
    ]);
    AlertRecipientRead::create([
        'alert_id' => $smsAlert->id,
        'recipient_id' => $recipient->id,
        'read_at' => now(),
        'acknowledged_via' => 'sms',
    ]);

    $migration = require base_path('database/migrations/2026_10_12_001210_backfill_acknowledged_notification_logs.php');
    $migration->up();
    $migration->up();

    expect(NotificationLog::where('channel', 'email')->where('status', NotificationLog::STATUS_DELIVERED)->count())
        ->toBe(1)
        ->and(NotificationLog::where('channel', 'sms')->where('status', NotificationLog::STATUS_DELIVERED)->count())
        ->toBe(1);
});
