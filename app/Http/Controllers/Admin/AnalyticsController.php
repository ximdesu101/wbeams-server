<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAlertEmailNotifications;
use App\Jobs\SendAlertSmsNotifications;
use App\Mail\AlertNotificationMail;
use App\Models\NotificationLog;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Recipient\Report;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    // ----------------------------------------------------------------
    // Alert Reports
    // ----------------------------------------------------------------

    /**
     * Alert delivery by channel (monthly, current year).
     * Returns: [{ month, inApp, sms, email }, ...]
     */
    public function alertChannel(): JsonResponse
    {
        $year = now()->year;

        // Build zeroed scaffold
        $months = collect(self::MONTHS)->map(fn ($name, $num) => [
            'month' => $name,
            'inApp' => 0,
            'sms' => 0,
            'email' => 0,
        ])->values()->all();

        $alerts = Alert::query()
            ->whereYear('sent_at', $year)
            ->get(['channels', 'sent_at']);

        foreach ($alerts as $alert) {
            $monthIndex = (int) Carbon::parse($alert->sent_at)->format('n') - 1;
            foreach ($alert->channels ?? [] as $channel) {
                if ($channel === 'web_push') {
                    $months[$monthIndex]['inApp']++;
                } elseif ($channel === 'sms') {
                    $months[$monthIndex]['sms']++;
                } elseif ($channel === 'email') {
                    $months[$monthIndex]['email']++;
                }
            }
        }

        return response()->json(['data' => $months]);
    }

    /**
     * Acknowledged alerts grouped by channel.
     * Returns: [{ channel, acknowledged, fill }, ...]
     */
    public function acknowledgeVia(): JsonResponse
    {
        $counts = AlertRecipientRead::query()
            ->selectRaw('acknowledged_via, COUNT(*) as total')
            ->groupBy('acknowledged_via')
            ->pluck('total', 'acknowledged_via');

        $data = [
            ['channel' => 'In-App', 'acknowledged' => (int) ($counts['in-app'] ?? 0), 'fill' => 'var(--chart-1)'],
            ['channel' => 'Email', 'acknowledged' => (int) ($counts['email'] ?? 0), 'fill' => 'var(--chart-2)'],
            // SMS acknowledgements are handled via a separate signed URL; track future values here
            ['channel' => 'SMS', 'acknowledged' => (int) ($counts['sms'] ?? 0), 'fill' => 'var(--chart-3)'],
        ];

        return response()->json(['data' => $data]);
    }

    public function emailMetrics(): JsonResponse
    {
        return response()->json(['data' => $this->deliveryMetrics('email')]);
    }

    public function emailDeliveryPerformance(): JsonResponse
    {
        return response()->json(['data' => $this->monthlyDeliveryPerformance('email')]);
    }

    public function smsMetrics(): JsonResponse
    {
        return response()->json(['data' => $this->deliveryMetrics('sms')]);
    }

    public function smsDeliveryPerformance(): JsonResponse
    {
        return response()->json(['data' => $this->monthlyDeliveryPerformance('sms')]);
    }

    /**
     * Delivery outcomes come from per-recipient notification records.
     * A provider acceptance is "sent"; only a channel acknowledgment confirms delivery.
     *
     * @return array<string, int|float>
     */
    private function deliveryMetrics(string $channel): array
    {
        $deliveries = NotificationLog::query()
            ->where('channel', $channel)
            ->whereYear('created_at', now()->year);

        $sent = (clone $deliveries)
            ->whereIn('status', [NotificationLog::STATUS_SENT, NotificationLog::STATUS_DELIVERED])
            ->count();
        $delivered = (clone $deliveries)
            ->where('status', NotificationLog::STATUS_DELIVERED)
            ->count();
        $failed = (clone $deliveries)
            ->where('status', NotificationLog::STATUS_FAILED)
            ->count();
        $pending = (clone $deliveries)
            ->whereIn('status', [NotificationLog::STATUS_QUEUED, NotificationLog::STATUS_PROCESSING])
            ->count();
        $jobClasses = [$channel === 'email' ? SendAlertEmailNotifications::class : SendAlertSmsNotifications::class];
        if ($channel === 'email') {
            $jobClasses[] = AlertNotificationMail::class;
        }
        $queue = $this->queueJobMetrics($jobClasses);
        $completedOrFailed = $sent + $failed;

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'jobs' => $queue['pending'],
            'failed' => $failed,
            'pending' => $pending,
            'processing_jobs' => $queue['processing'],
            'failed_jobs' => $queue['failed'],
            'delivery_rate' => $completedOrFailed > 0 ? round(($delivered / $completedOrFailed) * 100, 1) : 0,
            'failure_rate' => $completedOrFailed > 0 ? round(($failed / $completedOrFailed) * 100, 1) : 0,
        ];
    }

    /**
     * @return array<int, array{month: string, sent: int, delivered: int, failed: int}>
     */
    private function monthlyDeliveryPerformance(string $channel): array
    {
        $months = [];
        foreach (self::MONTHS as $name) {
            $months[] = [
                'month' => $name,
                'sent' => 0,
                'delivered' => 0,
                'failed' => 0,
            ];
        }

        $deliveries = NotificationLog::query()
            ->where('channel', $channel)
            ->whereYear('created_at', now()->year)
            ->get(['status', 'created_at']);

        foreach ($deliveries as $delivery) {
            $monthIndex = (int) Carbon::parse($delivery->created_at)->format('n') - 1;

            if (in_array($delivery->status, [
                NotificationLog::STATUS_SENT,
                NotificationLog::STATUS_DELIVERED,
            ], true)) {
                $months[$monthIndex]['sent']++;
            }

            if ($delivery->status === NotificationLog::STATUS_DELIVERED) {
                $months[$monthIndex]['delivered']++;
            } elseif ($delivery->status === NotificationLog::STATUS_FAILED) {
                $months[$monthIndex]['failed']++;
            }
        }

        return $months;
    }

    /**
     * @param  array<int, class-string>  $jobClasses
     * @return array{pending: int, processing: int, failed: int}
     */
    private function queueJobMetrics(array $jobClasses): array
    {
        $connectionName = config('queue.default');
        $queueConfig = config("queue.connections.{$connectionName}");

        abort_unless(
            ($queueConfig['driver'] ?? null) === 'database',
            503,
            'Per-channel queue metrics require Laravel\'s database queue driver.'
        );

        $queue = $queueConfig['queue'] ?? 'default';
        $queueDatabase = DB::connection($queueConfig['connection'] ?: config('database.default'));
        $jobs = $queueDatabase->table($queueConfig['table'] ?? 'jobs')
            ->where('queue', $queue);
        $jobNames = array_map(fn (string $jobClass): string => class_basename($jobClass), $jobClasses);
        $jobs->where(function ($query) use ($jobNames) {
            foreach ($jobNames as $jobName) {
                $query->orWhere('payload', 'like', '%'.$jobName.'%');
            }
        });
        $expiredBefore = (int) now()->timestamp - (int) ($queueConfig['retry_after'] ?? 90);

        $pending = (clone $jobs)
            ->where(function ($query) use ($expiredBefore) {
                $query->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<=', $expiredBefore);
            })
            ->count();
        $processing = (clone $jobs)
            ->where('reserved_at', '>', $expiredBefore)
            ->count();

        $failedConfig = config('queue.failed');
        $failedDatabase = DB::connection($failedConfig['database'] ?? config('database.default'));
        $failed = $failedDatabase->table($failedConfig['table'] ?? 'failed_jobs')
            ->where('queue', $queue)
            ->where(function ($query) use ($jobNames) {
                foreach ($jobNames as $jobName) {
                    $query->orWhere('payload', 'like', '%'.$jobName.'%');
                }
            })
            ->count();

        return [
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
        ];
    }

    /**
     * Emergency alerts grouped by category (pie chart).
     * Returns: [{ category, alerts, fill }, ...]
     */
    public function emergencyCategory(): JsonResponse
    {
        $chartColors = [
            'var(--chart-1)',
            'var(--chart-2)',
            'var(--chart-3)',
            'var(--chart-4)',
            'var(--chart-5)',
        ];

        // Count alerts per emergency category via the alert_types join
        $rows = Alert::query()
            ->join('alert_types', 'alerts.alert_type_id', '=', 'alert_types.id')
            ->join('emergency_categories', 'alert_types.emergency_category_id', '=', 'emergency_categories.id')
            ->selectRaw('emergency_categories.name as category_name, COUNT(alerts.id) as alerts_count')
            ->groupBy('emergency_categories.id', 'emergency_categories.name')
            ->orderByDesc('alerts_count')
            ->get();

        // Alerts whose alert_type is uncategorised
        $uncategorised = Alert::query()
            ->join('alert_types', 'alerts.alert_type_id', '=', 'alert_types.id')
            ->whereNull('alert_types.emergency_category_id')
            ->count();

        $data = $rows->values()->map(function ($row, $index) use ($chartColors) {
            return [
                'category' => $row->category_name,
                'alerts' => (int) $row->alerts_count,
                'fill' => $chartColors[$index % count($chartColors)],
            ];
        })->all();

        if ($uncategorised > 0) {
            $data[] = [
                'category' => 'Other',
                'alerts' => $uncategorised,
                'fill' => $chartColors[count($data) % count($chartColors)],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Response & delivery performance metrics.
     * - Avg response time: sent_at → first acknowledgement (in-app)
     * - Avg resolution time: sent_at → resolved_at
     * - Peak concurrent alerts: max simultaneous active alerts
     * - Avg alert delivery time: sent_at → any read_at (all channels)
     */
    public function responseMetrics(): JsonResponse
    {
        // Average response time (seconds): alert sent_at → first in-app acknowledgement
        $avgResponseSeconds = AlertRecipientRead::query()
            ->join('alerts', 'alerts.id', '=', 'alert_recipient_reads.alert_id')
            ->where('alert_recipient_reads.acknowledged_via', 'in-app')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, alerts.sent_at, alert_recipient_reads.read_at)) as avg_seconds')
            ->value('avg_seconds');

        // Average resolution time (seconds): alert sent_at → resolved_at
        $avgResolutionSeconds = Alert::query()
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, resolved_at)) as avg_seconds')
            ->value('avg_seconds');

        // Peak concurrent alerts (sweep-line over sent/resolved timestamps)
        $peakConcurrent = $this->calculatePeakConcurrentAlerts();

        // Avg delivery time: alert sent_at → any read_at (proxy for all channels)
        $avgDeliverySeconds = AlertRecipientRead::query()
            ->join('alerts', 'alerts.id', '=', 'alert_recipient_reads.alert_id')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, alerts.sent_at, alert_recipient_reads.read_at)) as avg_seconds')
            ->value('avg_seconds');

        return response()->json([
            'data' => [
                'avg_response_time' => $this->formatSeconds((float) $avgResponseSeconds),
                'avg_resolution_time' => $this->formatSeconds((float) $avgResolutionSeconds),
                'peak_concurrent_alerts' => $peakConcurrent,
                'avg_delivery_time' => $this->formatSeconds((float) $avgDeliverySeconds),
            ],
        ]);
    }

    /**
     * Geographic/area summary — report counts grouped by location.
     * Returns: [{ id, area, totalAlerts, activeAlerts, resolved, responseTime, resolutionTime, deliveryRate }, ...]
     */
    public function geographicSummary(): JsonResponse
    {
        $areas = Report::query()
            ->selectRaw('location')
            ->selectRaw('COUNT(*) as total_alerts')
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'acknowledged') THEN 1 ELSE 0 END) as active_alerts")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_alerts")
            ->selectRaw('AVG(CASE WHEN status_updated_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, status_updated_at) ELSE NULL END) as avg_response_seconds')
            ->selectRaw("AVG(CASE WHEN status = 'resolved' AND status_updated_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, status_updated_at) ELSE NULL END) as avg_resolution_seconds")
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->groupBy('location')
            ->orderByDesc('total_alerts')
            ->get();

        $data = $areas->values()->map(function ($area, $index) {
            $totalAlerts = (int) $area->total_alerts;
            $resolved = (int) $area->resolved_alerts;
            $deliveryRate = $totalAlerts > 0
                ? round(($resolved / $totalAlerts) * 100, 1).'%'
                : 'N/A';

            return [
                'id' => 'AREA-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'area' => $area->location,
                'totalAlerts' => $totalAlerts,
                'activeAlerts' => (int) $area->active_alerts,
                'resolved' => $resolved,
                'responseTime' => $this->formatSeconds((float) $area->avg_response_seconds),
                'resolutionTime' => $this->formatSeconds((float) $area->avg_resolution_seconds),
                'deliveryRate' => $deliveryRate,
            ];
        })->all();

        return response()->json(['data' => $data]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function formatSeconds(float $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $minutes = (int) floor($seconds / 60);
        $secs = (int) round($seconds % 60);

        if ($minutes === 0) {
            return "{$secs}s";
        }

        return "{$minutes}m {$secs}s";
    }

    private function calculatePeakConcurrentAlerts(): int
    {
        $alerts = Alert::query()
            ->where('status', '!=', 'cancelled')
            ->get(['sent_at', 'resolved_at']);

        if ($alerts->isEmpty()) {
            return 0;
        }

        $events = [];
        foreach ($alerts as $alert) {
            $events[] = [Carbon::parse($alert->sent_at)->timestamp, 1];
            $endTs = $alert->resolved_at
                ? Carbon::parse($alert->resolved_at)->timestamp
                : now()->timestamp;
            $events[] = [$endTs, -1];
        }

        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $peak = 0;
        $current = 0;
        foreach ($events as [$ts, $delta]) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
