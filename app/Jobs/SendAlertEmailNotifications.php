<?php

namespace App\Jobs;

use App\Mail\AlertNotificationMail;
use App\Models\Operator\Alert;
use App\Models\Recipient\Recipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendAlertEmailNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    public function handle(): void
    {
        $recipients = Recipient::whereIn('role', $this->alert->target_roles)->get();

        foreach ($recipients as $recipient) {
            $acknowledgeUrl = URL::signedRoute(
                'recipient.alerts.acknowledge-email',
                [
                    'alert' => $this->alert->id,
                    'recipient' => $recipient->id,
                ],
                now()->addDays(7),
            );

            Mail::to($recipient->email)
                ->queue(new AlertNotificationMail($this->alert, $recipient, $acknowledgeUrl));
        }
    }
}
