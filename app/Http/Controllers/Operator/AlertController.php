<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreAlertRequest;
use App\Jobs\SendAlertEmailNotifications;
use App\Models\Operator\Alert;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * List recent alerts dispatched by the authenticated operator (or all if preferred).
     * Supports optional search query.
     */
    public function index(Request $request): JsonResponse
    {
        $operator = $request->user();

        $query = Alert::with([
            'alertType:id,name,icon,color',
            'operator:id,first_name,last_name',
        ])
            ->where('operator_id', $operator->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('severity', 'like', "%{$search}%")
                    ->orWhereHas('alertType', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $alerts = $query->limit($limit)->get();

        $mapped = $alerts->map(function (Alert $alert) {
            $instructions = $alert->response_instructions ?? [];
            $instructionsText = implode("\n", array_filter(array_map('strval', $instructions)));

            return [
                'id' => $alert->id,
                'title' => $alert->title,
                'message' => $alert->message,
                'instructions' => $instructionsText,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'channels' => $alert->channels ?? [],
                'target_roles' => $alert->target_roles ?? [],
                'alert_type' => $alert->alertType
                    ? [
                        'id' => $alert->alertType->id,
                        'name' => $alert->alertType->name,
                        'icon' => $alert->alertType->icon,
                        'color' => $alert->alertType->color,
                    ]
                    : null,
                'sent_at' => $alert->sent_at->toIso8601String(),
                'sent_at_label' => $alert->sent_at->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            ];
        });

        return response()->json([
            'data' => $mapped,
        ]);
    }

    public function store(StoreAlertRequest $request): \Illuminate\Http\JsonResponse
    {
        $alert = Alert::create([
            ...$request->validated(),
            'operator_id' => $request->user()->id,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        SseNotifier::touch('alerts');
        SseNotifier::touch('recipient-alerts');

        if (in_array('email', $alert->channels, true)) {
            SendAlertEmailNotifications::dispatch($alert);
        }

        return response()->json($alert, 201);
    }
}