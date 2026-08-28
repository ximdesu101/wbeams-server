<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Recipient\Recipient;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AlertController extends Controller
{
    // ============================================================
    // 1. LIST - Alerts targeted to the recipient's role, with read
    //    state and acknowledged_via channel
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $reads = AlertRecipientRead::where('recipient_id', $recipient->id)
            ->get(['alert_id', 'acknowledged_via'])
            ->keyBy('alert_id');

        $alerts = Alert::whereJsonContains('target_roles', $recipient->role)
            ->with([
                'alertType:id,name,icon,color',
                'operator:id,first_name,last_name',
            ])
            ->orderByDesc('sent_at')
            ->get()
            ->map(function ($alert) use ($reads) {
                $read = $reads->get($alert->id);
                $alert->is_read = $read !== null;
                $alert->acknowledged_via = $read?->acknowledged_via;

                return $alert;
            });

        return response()->json(['data' => $alerts]);
    }

    // ============================================================
    // 2. PENDING - Unread alerts only, for the blocking
    //    acknowledgment dialog shown when a recipient enters
    // ============================================================
    public function pending(Request $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $alerts = Alert::whereJsonContains('target_roles', $recipient->role)
            ->whereDoesntHave('reads', function ($query) use ($recipient) {
                $query->where('recipient_id', $recipient->id);
            })
            ->with([
                'alertType:id,name,icon,color',
                'operator:id,first_name,last_name',
            ])
            ->orderByDesc('sent_at')
            ->get();

        return response()->json(['data' => $alerts]);
    }

    // ============================================================
    // 3. UNREAD COUNT - For the notification bell badge
    // ============================================================
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $count = Alert::whereJsonContains('target_roles', $recipient->role)
            ->whereDoesntHave('reads', function ($query) use ($recipient) {
                $query->where('recipient_id', $recipient->id);
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    // ============================================================
    // 4. MARK ONE AS READ — in-app acknowledgment
    // ============================================================
    public function markRead(Request $request, Alert $alert): Response
    {
        AlertRecipientRead::firstOrCreate(
            [
                'alert_id' => $alert->id,
                'recipient_id' => $request->user()->id,
            ],
            [
                'read_at' => now(),
                'acknowledged_via' => 'in-app',
            ]
        );

        SseNotifier::touch('recipient-alerts');

        return response()->noContent();
    }

    // ============================================================
    // 5. MARK ALL AS READ — in-app bulk acknowledgment
    // ============================================================
    public function markAllRead(Request $request): Response
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $unreadAlertIds = Alert::whereJsonContains('target_roles', $recipient->role)
            ->whereDoesntHave('reads', function ($query) use ($recipient) {
                $query->where('recipient_id', $recipient->id);
            })
            ->pluck('id');

        $rows = $unreadAlertIds->map(fn ($id) => [
            'alert_id' => $id,
            'recipient_id' => $recipient->id,
            'read_at' => now(),
            'acknowledged_via' => 'in-app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($rows->isNotEmpty()) {
            AlertRecipientRead::insert($rows->toArray());
        }

        SseNotifier::touch('recipient-alerts');

        return response()->noContent();
    }

    // ============================================================
    // 6. ACKNOWLEDGE VIA EMAIL — called from the signed link in the
    //    email notification; marks as read and records the channel
    // ============================================================
    public function acknowledgeViaEmail(Request $request, Alert $alert, Recipient $recipient): View|Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This acknowledgment link is invalid or has expired.');
        }

        AlertRecipientRead::firstOrCreate(
            [
                'alert_id' => $alert->id,
                'recipient_id' => $recipient->id,
            ],
            [
                'read_at' => now(),
                'acknowledged_via' => 'email',
            ]
        );

        SseNotifier::touch('recipient-alerts');

        return response()->view('emails.alert-acknowledged', [
            'alert' => $alert,
            'recipient' => $recipient,
        ]);
    }
}