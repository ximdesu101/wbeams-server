<?php

namespace App\Mail;

use App\Models\Operator\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OperatorPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Operator $operator,
        public string $resetUrl,
        public string $expiresAtLabel,
    ) {}

    public function build(): self
    {
        return $this->subject('Reset Your Operator Password')
            ->view('emails.operator-password-reset', [
                'operator' => $this->operator,
                'resetUrl' => $this->resetUrl,
                'expiresAtLabel' => $this->expiresAtLabel,
            ]);
    }
}