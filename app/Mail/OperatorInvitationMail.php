<?php

namespace App\Mail;

use App\Models\Operator\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OperatorInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Operator $operator,
        public string $activationUrl,
    ) {}

    public function build(): self
    {
        return $this->subject('Invitation to Activate Your Operator Account')
            ->view('emails.operator-invitation', [
                'operator' => $this->operator,
                'activationUrl' => $this->activationUrl,
                'expires_at' => $this->operator->activation_token_expires_at,
                'expires_in_hours' => now()->diffInHours($this->operator->activation_token_expires_at),
            ]);
    }
}