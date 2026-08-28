<?php

namespace App\Mail;

use App\Models\Recipient\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccessRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccessRequest $accessRequest,
    ) {}

    public function build(): self
    {
        return $this->subject('Your Access Request Has Been Rejected')
            ->view('emails.access-request-rejected', [
                'accessRequest' => $this->accessRequest,
            ]);
    }
}