<?php

namespace App\Jobs;

use App\Mail\AlertNotificationMail;
use App\Models\NotificationLog;
use App\Models\Operator\Alert;
use App\Models\Recipient\Recipient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use LogicException;
use Throwable;

class SendAlertEmailNotifications implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public int $uniqueFor = 3600;

    public ?Alert $alert = null;

    public ?int $notificationLogId = null;

    public function __construct(Alert|int $deliveryOrAlert)
    {
        if ($deliveryOrAlert instanceof Alert) {
            $this->alert = $deliveryOrAlert;
        } else {
            $this->notificationLogId = $deliveryOrAlert;
        }
    }

    public function uniqueId(): string
    {
        return (string) ($this->notificationLogId ?? 'alert-'.$this->alert?->id);
    }

    public function handle(): void
    {
        if ($this->notificationLogId === null) {
            $this->dispatchLegacyDeliveries();

            return;
        }

        $delivery = NotificationLog::with(['alert', 'recipient'])->findOrFail($this->notificationLogId);

        if (in_array($delivery->status, [
            NotificationLog::STATUS_SENT,
            NotificationLog::STATUS_DELIVERED,
        ], true)) {
            return;
        }

        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 90);
        $claimableStatuses = [
            NotificationLog::STATUS_QUEUED,
            NotificationLog::STATUS_FAILED,
        ];

        $claim = NotificationLog::query()
            ->whereKey($delivery->id)
            ->where(function ($query) use ($claimableStatuses, $retryAfter) {
                $query->whereIn('status', $claimableStatuses)
                    ->orWhere(function ($query) use ($retryAfter) {
                        $query->where('status', NotificationLog::STATUS_PROCESSING)
                            ->where('updated_at', '<=', now()->subSeconds($retryAfter));
                    });
            });
        $claimed = $claim
            ->update([
                'status' => NotificationLog::STATUS_PROCESSING,
                'failed_at' => null,
                'updated_at' => now(),
            ]);

        if (! $claimed) {
            return;
        }

        try {
            $acknowledgeUrl = URL::signedRoute(
                'recipient.alerts.acknowledge-email',
                [
                    'alert' => $delivery->alert->id,
                    'recipient' => $delivery->recipient->id,
                ],
                now()->addDays(7),
            );

            Mail::to($delivery->recipient->email)
                ->send(new AlertNotificationMail($delivery->alert, $delivery->recipient, $acknowledgeUrl));

            NotificationLog::query()
                ->whereKey($delivery->id)
                ->where('status', NotificationLog::STATUS_PROCESSING)
                ->update([
                    'status' => NotificationLog::STATUS_SENT,
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            NotificationLog::query()
                ->whereKey($delivery->id)
                ->where('status', NotificationLog::STATUS_PROCESSING)
                ->update([
                    'status' => NotificationLog::STATUS_QUEUED,
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->notificationLogId === null) {
            return;
        }

        NotificationLog::query()
            ->whereKey($this->notificationLogId)
            ->whereIn('status', [
                NotificationLog::STATUS_QUEUED,
                NotificationLog::STATUS_PROCESSING,
            ])
            ->update([
                'status' => NotificationLog::STATUS_FAILED,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function dispatchLegacyDeliveries(): void
    {
        if ($this->alert === null) {
            throw new LogicException('The email job is missing its delivery or legacy alert data.');
        }

        $recipients = Recipient::query()->whereIn('role', $this->alert->target_roles)->get();

        foreach ($recipients as $recipient) {
            $delivery = NotificationLog::firstOrCreate(
                [
                    'alert_id' => $this->alert->id,
                    'recipient_id' => $recipient->id,
                    'channel' => 'email',
                ],
                ['status' => NotificationLog::STATUS_QUEUED]
            );

            self::dispatch($delivery->id)->afterCommit();
        }
    }
}
