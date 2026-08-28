<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOperatorRequest;
use App\Models\Operator\Alert;
use App\Models\Operator\AlertRecipientRead;
use App\Models\Operator\Operator;
use App\Enums\OperatorStatus;
use App\Models\Recipient\Report;
use App\Services\OperatorInvitationService;
use App\Support\SseNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperatorController extends Controller
{
    public function __construct(
        protected OperatorInvitationService $invitationService
    ) {
    }

    // ============================================================
    // 1. READ / LIST - Display all operators with filters and status counts
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $expiredCount = Operator::where('status', OperatorStatus::Inactive)
            ->whereNotNull('activation_token_expires_at')
            ->where('activation_token_expires_at', '<', now())
            ->update([
                'status' => OperatorStatus::Expired,
                'expired_at' => now(),
            ]);
        if ($expiredCount > 0) {
            SseNotifier::touch('operators');
        }
        $query = Operator::query();

        if ($request->filled('status') && in_array($request->status, array_column(OperatorStatus::cases(), 'value'))) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('contact_number', 'LIKE', "%{$search}%")
                    ->orWhere('operator_id', 'LIKE', "%{$search}%");
            });
        }

        $operators = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => $operators->items(),
            'meta' => [
                'current_page' => $operators->currentPage(),
                'last_page' => $operators->lastPage(),
                'total' => $operators->total(),
                'status_counts' => (function () {
                    $counts = Operator::selectRaw('status, COUNT(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status');
                    return [
                        'active' => (int) ($counts[OperatorStatus::Active->value] ?? $counts['active'] ?? 0),
                        'inactive' => (int) ($counts[OperatorStatus::Inactive->value] ?? $counts['inactive'] ?? 0),
                        'expired' => (int) ($counts[OperatorStatus::Expired->value] ?? $counts['expired'] ?? 0),
                        'deactivated' => (int) ($counts[OperatorStatus::Deactivated->value] ?? $counts['deactivated'] ?? 0),
                    ];
                })(),
            ],
        ]);
    }

    // ============================================================
    // 2. READ / SHOW - Display a single operator
    // ============================================================
    public function show(Operator $operator): JsonResponse
    {
        if ($operator->status === OperatorStatus::Inactive && $operator->is_token_expired) {
            $operator->markAsExpired();
            $operator->refresh();
        }

        return response()->json([
            'data' => $this->formatOperatorData($operator),
        ]);
    }

    // ============================================================
    // 3. CREATE / STORE - Add a new operator and send invitation
    // ============================================================
    public function store(StoreOperatorRequest $request): JsonResponse
    {
        $plainToken = $this->generateActivationToken();

        $operator = DB::transaction(function () use ($request, $plainToken) {
            $latestOperator = Operator::latest('id')->lockForUpdate()->first();
            $nextId = $latestOperator ? intval(substr($latestOperator->operator_id, 4)) + 1 : 1;
            $operatorId = 'OPR-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);

            return Operator::create([
                'operator_id' => $operatorId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'contact_number' => $request->contact_number,
                'email' => $request->email,
                'activation_token' => hash('sha256', $plainToken),
                'activation_token_expires_at' => now()->addHours(24),
                'status' => OperatorStatus::Inactive,
            ]);
        });

        $this->invitationService->sendInvitation($operator, $plainToken);
        SseNotifier::touch('operators');

        return response()->json([
            'message' => 'Operator account created successfully. Invitation email sent.',
            'data' => $this->formatOperatorData($operator),
        ], 201);
    }

    // ============================================================
    // 4. UPDATE / RESEND - Resend invitation to an operator
    // ============================================================
    public function resendInvitation(Operator $operator): JsonResponse
    {
        if ($operator->status === OperatorStatus::Active) {
            return response()->json([
                'message' => 'This account is already activated.',
            ], 422);
        }

        $plainToken = $this->generateActivationToken();

        $operator->update([
            'activation_token' => hash('sha256', $plainToken),
            'activation_token_expires_at' => now()->addHours(24),
            'status' => OperatorStatus::Inactive,
        ]);

        $this->invitationService->sendInvitation($operator, $plainToken);
        SseNotifier::touch('operators');

        return response()->json([
            'message' => 'Invitation resent successfully.',
            'data' => $this->formatOperatorData($operator),
        ]);
    }

    public function activity(Operator $operator): JsonResponse
    {
        $months = collect(range(1, 12))->mapWithKeys(fn($month) => [
            str_pad((string) $month, 2, '0', STR_PAD_LEFT) => [
                'month' => Carbon::createFromDate(now()->year, $month, 1)->format('F'),
                'alertsSent' => 0,
                'alertsAcknowledged' => 0,
            ]
        ])->toArray();

        $alertsSent = Alert::where('operator_id', $operator->id)
            ->whereYear('sent_at', now()->year)
            ->selectRaw('MONTH(sent_at) as month, COUNT(*) as total')
            ->groupByRaw('MONTH(sent_at)')
            ->get();

        foreach ($alertsSent as $row) {
            $key = str_pad((string) $row->month, 2, '0', STR_PAD_LEFT);
            if (isset($months[$key])) {
                $months[$key]['alertsSent'] = $row->total;
            }
        }

        $acknowledged = AlertRecipientRead::whereHas('alert', fn($query) => $query->where('operator_id', $operator->id))
            ->whereYear('read_at', now()->year)
            ->selectRaw('MONTH(read_at) as month, COUNT(*) as total')
            ->groupByRaw('MONTH(read_at)')
            ->get();

        foreach ($acknowledged as $row) {
            $key = str_pad((string) $row->month, 2, '0', STR_PAD_LEFT);
            if (isset($months[$key])) {
                $months[$key]['alertsAcknowledged'] = $row->total;
            }
        }

        return response()->json([
            'data' => array_values($months),
        ]);
    }

    public function dailyActivity(Operator $operator): JsonResponse
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        // Build empty week as a plain array (Mon → Sun)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $monday->copy()->addDays($i)->toDateString();
            $days[$date] = [
                'date' => $date,
                'sentAlert' => 0,
                'acknowledged' => 0,
                'resolved' => 0,
                'rejected' => 0,
            ];
        }

        // Alerts sent by this operator this week
        $alerts = Alert::query()
            ->where('operator_id', $operator->id)
            ->whereBetween('sent_at', [$monday, $sunday])
            ->get(['sent_at']);

        foreach ($alerts as $alert) {
            $day = Carbon::parse($alert->sent_at)->toDateString();
            if (isset($days[$day])) {
                $days[$day]['sentAlert']++;
            }
        }

        // Reports handled by this operator this week
        $reports = Report::query()
            ->where('handled_by_operator_id', $operator->id)
            ->whereIn('status', ['acknowledged', 'resolved', 'rejected'])
            ->whereNotNull('status_updated_at')
            ->whereBetween('status_updated_at', [$monday, $sunday])
            ->get(['status', 'status_updated_at']);

        foreach ($reports as $report) {
            $day = Carbon::parse($report->status_updated_at)->toDateString();
            if (!isset($days[$day])) {
                continue;
            }

            if ($report->status === 'acknowledged') {
                $days[$day]['acknowledged']++;
            } elseif ($report->status === 'resolved') {
                $days[$day]['resolved']++;
            } elseif ($report->status === 'rejected') {
                $days[$day]['rejected']++;
            }
        }

        return response()->json([
            'data' => array_values($days),
            'meta' => [
                'week_start' => $monday->toDateString(),
                'week_end' => $sunday->toDateString(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Operator $operator): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,deactivated'],
        ]);

        // Admin can only activate/deactivate accounts that have already been
        // activated by the operator themselves (activated_at is set).
        // Pending (inactive) or expired accounts cannot be forced active.
        if ($operator->activated_at === null) {
            return response()->json([
                'message' => 'This account has not been activated by the operator yet. The operator must complete activation first.',
            ], 422);
        }

        $status = $validated['status'];

        if ($status === 'deactivated') {
            if ($operator->status !== OperatorStatus::Active) {
                return response()->json([
                    'message' => 'Only an active account can be deactivated.',
                ], 422);
            }

            $operator->update([
                'status' => OperatorStatus::Deactivated,
                'activation_token' => null,
                'activation_token_expires_at' => null,
            ]);
        } else {
            // Reactivate a previously deactivated account
            if ($operator->status !== OperatorStatus::Deactivated) {
                return response()->json([
                    'message' => 'Only a deactivated account can be reactivated.',
                ], 422);
            }

            $operator->update([
                'status' => OperatorStatus::Active,
                // Keep the original activated_at (do not overwrite)
                'activation_token' => null,
                'activation_token_expires_at' => null,
            ]);
        }

        SseNotifier::touch('operators');

        return response()->json([
            'message' => 'Operator account status updated successfully.',
            'data' => $this->formatOperatorData($operator->refresh()),
        ]);
    }

    public function destroy(Operator $operator): JsonResponse
    {
        $operator->delete();
        SseNotifier::touch('operators');

        return response()->json([
            'message' => 'Operator account deleted successfully.',
        ]);
    }

    // ============================================================
    // 6. HELPER METHODS - Supporting functions
    // ============================================================
    protected function generateActivationToken(): string
    {
        return Str::random(64);
    }
    /**
     * @return array<string, mixed>
     */
    protected function formatOperatorData(Operator $operator): array
    {
        return [
            'operator_id' => $operator->operator_id,
            'first_name' => $operator->first_name,
            'last_name' => $operator->last_name,
            'full_name' => $operator->full_name,
            'email' => $operator->email,
            'contact_number' => $operator->contact_number,
            'status' => $operator->status,
            'status_label' => $operator->status_label,
            'is_token_expired' => $operator->is_token_expired,
            'created_at' => $operator->created_at,
            'activated_at' => $operator->activated_at,
            'expired_at' => $operator->expired_at,
            'token_expires_at' => $operator->activation_token_expires_at,
        ];
    }
}