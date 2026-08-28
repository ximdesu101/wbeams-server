<?php

namespace App\Mail;

use App\Models\Operator\Alert;
use App\Models\Recipient\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Alert $alert,
        public Recipient $recipient,
        public string $acknowledgeUrl,
    ) {}

    public function envelope(): Envelope
    {
        $severityLabel = ucfirst($this->alert->severity);

        return new Envelope(
            subject: "[{$severityLabel}] Emergency Alert: {$this->alert->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-notification',
        );
    }
}
