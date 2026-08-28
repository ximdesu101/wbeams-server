<?php

namespace App\Services;

use App\Mail\OperatorInvitationMail;
use App\Models\Operator\Operator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class OperatorInvitationService
{
    public function sendInvitation(Operator $operator, string $plainToken): void
    {
        try {
            Mail::to($operator->email)->queue(
                new OperatorInvitationMail($operator, $this->generateActivationUrl($operator, $plainToken))
            );

            Log::info('Invitation queued for operator', [
                'operator_id' => $operator->operator_id,
                'email' => $operator->email,
                'expires_at' => $operator->activation_token_expires_at,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to queue operator invitation', [
                'operator_id' => $operator->operator_id,
                'email' => $operator->email,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to send invitation email.', previous: $e);
        }
    }

    protected function generateActivationUrl(Operator $operator, string $plainToken): string
    {
        return config('app.frontend_url') . '/operator/activate?token=' . $plainToken;
    }
}