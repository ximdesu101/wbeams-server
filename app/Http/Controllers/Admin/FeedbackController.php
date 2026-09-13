<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recipient\AlertFeedback;
use App\Models\Recipient\OperatorFeedback;
use App\Models\Recipient\SystemFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FeedbackController extends Controller
{
    /**
     * List all feedback with search, type, rating, and date filters.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all'); // all | alert | operator | system
        $search = $request->input('search');
        $rating = $request->input('rating'); // like|dislike or 1-5
        $from = $request->input('from');
        $to = $request->input('to');

        $items = collect();

        if ($type === 'all' || $type === 'alert') {
            $query = AlertFeedback::with([
                'recipient:id,first_name,last_name,email,role',
                'alert:id,title,severity,operator_id,sent_at',
                'alert.operator:id,first_name,last_name',
            ])->orderByDesc('updated_at');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('recipient', function ($rq) use ($search) {
                            $rq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('alert', function ($aq) use ($search) {
                            $aq->where('title', 'like', "%{$search}%");
                        });
                });
            }

            if ($rating === 'like' || $rating === 'dislike') {
                $query->where('rating', $rating);
            }

            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }

            $items = $items->merge(
                $query->get()->map(fn (AlertFeedback $f) => $this->formatAlert($f))
            );
        }

        if ($type === 'all' || $type === 'operator') {
            $query = OperatorFeedback::with([
                'recipient:id,first_name,last_name,email,role',
                'operator:id,first_name,last_name',
            ])->orderByDesc('updated_at');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('recipient', function ($rq) use ($search) {
                            $rq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($rating !== null && $rating !== '' && is_numeric($rating)) {
                $query->where('rating', (int) $rating);
            }

            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }

            $items = $items->merge(
                $query->get()->map(fn (OperatorFeedback $f) => $this->formatOperator($f))
            );
        }

        if ($type === 'all' || $type === 'system') {
            $query = SystemFeedback::with([
                'recipient:id,first_name,last_name,email,role',
            ])->orderByDesc('updated_at');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('recipient', function ($rq) use ($search) {
                            $rq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($rating !== null && $rating !== '' && is_numeric($rating)) {
                $query->where('rating', (int) $rating);
            }

            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }

            $items = $items->merge(
                $query->get()->map(fn (SystemFeedback $f) => $this->formatSystem($f))
            );
        }

        $items = $items->sortByDesc(function ($item) {
            return $item['updatedAt'] ?? $item['submittedAt'] ?? '';
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
            ],
        ]);
    }

    /**
     * Summary statistics calculated from all feedback.
     */
    public function stats(): JsonResponse
    {
        $alertTotal = AlertFeedback::count();
        $helpful = AlertFeedback::where('rating', 'like')->count();
        $notHelpful = AlertFeedback::where('rating', 'dislike')->count();

        $avgOperator = OperatorFeedback::avg('rating');
        $avgSystem = SystemFeedback::avg('rating');

        return response()->json([
            'data' => [
                'totalFeedback' => $alertTotal + OperatorFeedback::count() + SystemFeedback::count(),
                'helpfulAlerts' => $helpful,
                'notHelpfulAlerts' => $notHelpful,
                'alertFeedbackCount' => $alertTotal,
                'operatorFeedbackCount' => OperatorFeedback::count(),
                'systemFeedbackCount' => SystemFeedback::count(),
                'averageOperatorRating' => $avgOperator !== null
                    ? round((float) $avgOperator, 1)
                    : 0,
                'averageSystemRating' => $avgSystem !== null
                    ? round((float) $avgSystem, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * Single feedback record detail (type + id).
     */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $record = match ($type) {
            'alert' => AlertFeedback::with([
                'recipient:id,first_name,last_name,email,role',
                'alert:id,title,message,severity,operator_id,sent_at',
                'alert.operator:id,first_name,last_name',
            ])->find($id),
            'operator' => OperatorFeedback::with([
                'recipient:id,first_name,last_name,email,role',
                'operator:id,first_name,last_name',
            ])->find($id),
            'system' => SystemFeedback::with([
                'recipient:id,first_name,last_name,email,role',
            ])->find($id),
            default => null,
        };

        if (! $record) {
            return response()->json(['message' => 'Feedback not found.'], 404);
        }

        $data = match ($type) {
            'alert' => $this->formatAlert($record),
            'operator' => $this->formatOperator($record),
            'system' => $this->formatSystem($record),
        };

        return response()->json(['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function formatAlert(AlertFeedback $f): array
    {
        $recipient = $f->recipient;
        $alert = $f->alert;
        $operator = $alert?->operator;

        return [
            'id' => $f->id,
            'type' => 'alert',
            'rating' => $f->rating,
            'displayRating' => $f->rating === 'like' ? 'Helpful' : 'Not Helpful',
            'comment' => $f->comment,
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
            'recipient' => $recipient ? [
                'id' => $recipient->id,
                'name' => trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')),
                'email' => $recipient->email,
                'role' => $recipient->role,
            ] : null,
            'alert' => $alert ? [
                'id' => $alert->id,
                'title' => $alert->title,
                'severity' => $alert->severity,
                'sentAt' => $alert->sent_at?->toIso8601String(),
            ] : null,
            'operator' => $operator ? [
                'id' => $operator->id,
                'name' => trim(($operator->first_name ?? '').' '.($operator->last_name ?? '')),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatOperator(OperatorFeedback $f): array
    {
        $recipient = $f->recipient;
        $operator = $f->operator;

        return [
            'id' => $f->id,
            'type' => 'operator',
            'rating' => $f->rating,
            'displayRating' => $f->rating.'/5',
            'comment' => $f->comment,
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
            'recipient' => $recipient ? [
                'id' => $recipient->id,
                'name' => trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')),
                'email' => $recipient->email,
                'role' => $recipient->role,
            ] : null,
            'operator' => $operator ? [
                'id' => $operator->id,
                'name' => trim(($operator->first_name ?? '').' '.($operator->last_name ?? '')),
            ] : null,
            'alert' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatSystem(SystemFeedback $f): array
    {
        $recipient = $f->recipient;

        return [
            'id' => $f->id,
            'type' => 'system',
            'rating' => $f->rating,
            'displayRating' => $f->rating.'/5',
            'comment' => $f->comment,
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
            'recipient' => $recipient ? [
                'id' => $recipient->id,
                'name' => trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')),
                'email' => $recipient->email,
                'role' => $recipient->role,
            ] : null,
            'operator' => null,
            'alert' => null,
        ];
    }
}
