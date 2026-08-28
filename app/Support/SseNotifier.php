<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class SseNotifier
{
    public const CHANNELS = [
        'emergency-categories',
        'alert-types',
        'access-requests',
        'masterlist',
        'operators',
        'recipient-alerts',
        'alerts',
        'reports',
        'recipients',
    ];

    /** Channels streamed on GET /admin/sse */
    public const ADMIN_CHANNELS = [
        'emergency-categories',
        'alert-types',
        'access-requests',
        'masterlist',
        'operators',
        'alerts',
        'reports',
        'recipients',
    ];

    /** Channels streamed on GET /operator/sse */
    public const OPERATOR_CHANNELS = [
        'emergency-categories',
        'alert-types',
        'alerts',
        'reports',
    ];

    /** Channels streamed on GET /recipient/sse */
    public const RECIPIENT_CHANNELS = [
        'recipient-alerts',
        'reports',
    ];

    public static function touch(string $channel): void
    {
        Cache::put("sse:{$channel}:updated_at", now()->toISOString(), now()->addHour());
    }

    public static function lastUpdated(string $channel): ?string
    {
        return Cache::get("sse:{$channel}:updated_at");
    }
}