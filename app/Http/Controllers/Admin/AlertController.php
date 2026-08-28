<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Alert::with([
            'alertType:id,name,icon,color',
            'operator:id,first_name,last_name',
        ])->orderByDesc('sent_at');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('operator', function ($oq) use ($search) {
                        $oq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('alertType', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('severity') && $request->severity !== 'all') {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $alerts = $query->paginate($perPage);

        $mapped = $alerts->getCollection()->map(function ($alert) {
            return [
                'id'            => $alert->id,
                'EmergencyType' => $alert->alertType->name ?? $alert->title,
                'Description'   => $alert->message ?? $alert->response_instructions,
                'location'      => $alert->response_instructions['location'] ?? '',
                'severity'     => $alert->severity,
                'status'        => $alert->status,
                'reportedBy'    => trim(($alert->operator->first_name ?? '') . ' ' . ($alert->operator->last_name ?? '')),
                'date'          => $alert->sent_at->toDateString(),
            ];
        });

        return response()->json([
            'data' => $mapped,
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
                'per_page'     => $alerts->perPage(),
                'total'        => $alerts->total(),
            ],
        ]);
    }

    /**
     * Lightweight counts – never loads the full table.
     */
    public function stats(): JsonResponse
    {
        $total = Alert::count();

        $byStatus = Alert::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'total'      => $total,
                'sent'       => (int) ($byStatus['sent'] ?? 0),
                'resolved'   => (int) ($byStatus['resolved'] ?? 0),
                'cancelled'  => (int) ($byStatus['cancelled'] ?? 0),
                // Keep "acknowledged" key so existing UI doesn't break
                // (real acknowledgements live in alert_recipient_reads)
                'acknowledged' => (int) AlertRecipientRead::count(),
            ],
        ]);
    }

    public function dispatchStats(): JsonResponse
    {
        $channelKeys = ['email', 'web_push', 'sms'];
        $totals = array_fill_keys($channelKeys, 0);
        $acked  = array_fill_keys($channelKeys, 0);

        // Efficient channel counts (still scans channels JSON, but only the one column)
        // If this becomes slow later, normalize channels into a pivot table.
        $alerts = Alert::query()->get(['channels']);

        foreach ($alerts as $alert) {
            $channels = $alert->channels ?? [];
            foreach ($channels as $channel) {
                if (isset($totals[$channel])) {
                    $totals[$channel]++;
                }
            }
        }

        $ackCounts = AlertRecipientRead::query()
            ->selectRaw('acknowledged_via, COUNT(*) as total')
            ->groupBy('acknowledged_via')
            ->pluck('total', 'acknowledged_via');

        $acked['email']    = (int) ($ackCounts['email'] ?? 0);
        $acked['web_push'] = (int) ($ackCounts['in-app'] ?? 0);
        $acked['sms']      = 0;

        $channels = [
            [
                'key'       => 'email',
                'name'      => 'Email',
                'total'     => $totals['email'],
                'delivered' => $totals['email'],
                'acked'     => $acked['email'],
                'queued'    => 0,
            ],
            [
                'key'       => 'web_push',
                'name'      => 'In-app',
                'total'     => $totals['web_push'],
                'delivered' => $totals['web_push'],
                'acked'     => $acked['web_push'],
                'queued'    => 0,
            ],
            [
                'key'       => 'sms',
                'name'      => 'SMS',
                'total'     => $totals['sms'],
                'delivered' => $totals['sms'],
                'acked'     => $acked['sms'],
                'queued'    => 0,
            ],
        ];

        return response()->json([
            'data' => [
                'channels'       => $channels,
                'total_messages' => array_sum($totals),
                'total_acked'    => array_sum($acked),
            ],
        ]);
    }

    public function destroy(Alert $alert): JsonResponse
    {
        $alert->delete();
        SseNotifier::touch('alerts');

        return response()->json([
            'message' => 'Sent alert deleted successfully.',
        ]);
    }
}