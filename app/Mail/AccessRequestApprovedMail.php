<?php

namespace App\Mail;

use App\Models\Recipient\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccessRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccessRequest $accessRequest,
    ) {}

    public function build(): self
    {
        return $this->subject('Your Access Request Has Been Approved')
            ->view('emails.access-request-approved', [
                'accessRequest' => $this->accessRequest,
            ]);
    }
}