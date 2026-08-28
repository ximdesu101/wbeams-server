<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SseNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    /**
     * Single-channel stream (legacy per-resource routes).
     */
    public function stream(Request $request, string $channel): StreamedResponse
    {
        abort_unless(in_array($channel, SseNotifier::CHANNELS, true), 404);

        return $this->streamChannels([$channel]);
    }

    /**
     * Multiplexed admin bus — all admin channels on one connection.
     */
    public function adminStream(Request $request): StreamedResponse
    {
        return $this->streamChannels(SseNotifier::ADMIN_CHANNELS);
    }

    /**
     * Multiplexed operator bus.
     */
    public function operatorStream(Request $request): StreamedResponse
    {
        return $this->streamChannels(SseNotifier::OPERATOR_CHANNELS);
    }

    /**
     * Multiplexed recipient bus.
     */
    public function recipientStream(Request $request): StreamedResponse
    {
        return $this->streamChannels(SseNotifier::RECIPIENT_CHANNELS);
    }

    /**
     * @param  list<string>  $channels
     */
    private function streamChannels(array $channels): StreamedResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return response()->stream(function () use ($channels) {
            ignore_user_abort(true);
            set_time_limit(0);

            @ini_set('max_execution_time', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            ob_implicit_flush(true);

            $lastSeen = array_fill_keys($channels, null);
            $secondsElapsed = 0;

            try {
                while (true) {
                    if (connection_aborted()) {
                        break;
                    }

                    foreach ($channels as $channel) {
                        $updatedAt = SseNotifier::lastUpdated($channel);

                        if ($updatedAt !== null && $updatedAt !== $lastSeen[$channel]) {
                            $lastSeen[$channel] = $updatedAt;

                            echo "event: updated\n";
                            echo 'data: ' . json_encode([
                                'channel' => $channel,
                                'updated_at' => $updatedAt,
                            ]) . "\n\n";

                            if (ob_get_level() > 0) {
                                @ob_flush();
                            }

                            flush();
                        }
                    }

                    if ($secondsElapsed % 15 === 0) {
                        echo ": heartbeat\n\n";

                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }

                        flush();
                    }

                    sleep(1);
                    $secondsElapsed++;

                    if ($secondsElapsed >= 600) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('SSE Stream Error', [
                    'channels' => $channels,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}