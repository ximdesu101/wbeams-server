<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PhilSmsService
{
    /**
     * @return array<string, mixed>
     */
    public function send(string $recipient, string $message): array
    {
        $apiToken = config('services.philsms.api_token');
        $senderId = config('services.philsms.sender_id');
        $endpoint = config('services.philsms.endpoint');

        Log::debug('PhilSMS config check', [
            'token_length' => strlen((string) $apiToken),
            'token_prefix' => substr((string) $apiToken, 0, 6),
            'sender_id' => $senderId,
            'endpoint' => $endpoint,
        ]);

        if (blank($apiToken) || blank($senderId)) {
            throw new RuntimeException('PhilSMS is not configured. Set PHILSMS_API_TOKEN and PHILSMS_SENDER_ID.');
        }

        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'recipient' => $recipient,
                    'sender_id' => $senderId,
                    'type' => 'plain',
                    'message' => $message,
                ])
                ->throw();

            $payload = $response->json();

            Log::debug('PhilSMS raw response', [
                'status_code' => $response->status(),
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
                $errorMessage = is_array($payload)
                    ? (string) ($payload['message'] ?? 'Unknown PhilSMS error.')
                    : 'Unknown PhilSMS error.';

                throw new RuntimeException($errorMessage);
            }

            Log::info('PhilSMS message sent', [
                'recipient' => $recipient,
                'response' => $payload,
            ]);

            return $payload;
        } catch (RequestException $exception) {
            Log::error('PhilSMS HTTP error', [
                'recipient' => $recipient,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json() ?? $exception->response?->body(),
                'raw' => $exception->response?->body(),
            ]);

            throw new RuntimeException('Failed to send SMS via PhilSMS.', previous: $exception);
        }
    }
}
