<?php

namespace App\Jobs;

use App\Models\Operator\Alert;
use App\Models\Recipient\Recipient;
use App\Services\PhilSmsService;
use App\Support\AlertSmsMessage;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendAlertSmsNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    public function handle(PhilSmsService $philSmsService): void
    {
        $recipients = Recipient::whereIn('role', $this->alert->target_roles)->get();

        foreach ($recipients as $recipient) {
            $phoneNumber = PhilippinePhoneNumber::normalize($recipient->contact_number);

            if ($phoneNumber === null) {
                Log::warning('Skipping alert SMS for recipient without a valid contact number', [
                    'alert_id' => $this->alert->id,
                    'recipient_id' => $recipient->id,
                ]);

                continue;
            }

            $acknowledgeUrl = URL::signedRoute(
                'recipient.alerts.acknowledge-sms',
                [
                    'alert' => $this->alert->id,
                    'recipient' => $recipient->id,
                ],
                now()->addDays(7),
            );

            $message = AlertSmsMessage::build($this->alert, $recipient, $acknowledgeUrl);

            $philSmsService->send($phoneNumber, $message);
        }
    }
}
