<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\Operator\Alert;
use App\Models\Recipient\AlertFeedback;
use App\Models\Recipient\OperatorFeedback;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\SystemFeedback;
use App\Services\UserLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    // ============================================================
    // ALERT FEEDBACK — one record per (alert, recipient)
    // ============================================================

    public function showAlertFeedback(Request $request, Alert $alert): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $feedback = AlertFeedback::where('alert_id', $alert->id)
            ->where('recipient_id', $recipient->id)
            ->first();

        return response()->json([
            'data' => $feedback ? $this->formatAlertFeedback($feedback) : null,
        ]);
    }

    public function storeAlertFeedback(Request $request, Alert $alert): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $validated = $request->validate([
            'rating' => ['required', Rule::in(['like', 'dislike'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $comment = isset($validated['comment']) ? trim((string) $validated['comment']) : null;
        if ($comment === '') {
            $comment = null;
        }

        $feedback = AlertFeedback::updateOrCreate(
            [
                'alert_id' => $alert->id,
                'recipient_id' => $recipient->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $comment,
            ]
        );

        UserLogService::log($recipient, 'Submitted alert feedback');

        return response()->json([
            'message' => $feedback->wasRecentlyCreated
                ? 'Feedback submitted successfully.'
                : 'Feedback updated successfully.',
            'data' => $this->formatAlertFeedback($feedback),
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    // ============================================================
    // OPERATOR FEEDBACK — one record per recipient
    // ============================================================

    public function showOperatorFeedback(Request $request): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $feedback = OperatorFeedback::where('recipient_id', $recipient->id)->first();

        return response()->json([
            'data' => $feedback ? $this->formatOperatorFeedback($feedback) : null,
        ]);
    }

    public function storeOperatorFeedback(Request $request): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
        ]);

        $comment = isset($validated['comment']) ? trim((string) $validated['comment']) : null;
        if ($comment === '') {
            $comment = null;
        }

        $feedback = OperatorFeedback::updateOrCreate(
            ['recipient_id' => $recipient->id],
            [
                'rating' => (int) $validated['rating'],
                'comment' => $comment,
                'operator_id' => $validated['operator_id'] ?? null,
            ]
        );

        UserLogService::log($recipient, 'Submitted operator feedback');

        return response()->json([
            'message' => $feedback->wasRecentlyCreated
                ? 'Operator feedback submitted successfully.'
                : 'Operator feedback updated successfully.',
            'data' => $this->formatOperatorFeedback($feedback),
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    // ============================================================
    // SYSTEM FEEDBACK — one record per recipient
    // ============================================================

    public function showSystemFeedback(Request $request): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $feedback = SystemFeedback::where('recipient_id', $recipient->id)->first();

        return response()->json([
            'data' => $feedback ? $this->formatSystemFeedback($feedback) : null,
        ]);
    }

    public function storeSystemFeedback(Request $request): JsonResponse
    {
        /** @var Recipient $recipient */
        $recipient = $request->user();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $comment = isset($validated['comment']) ? trim((string) $validated['comment']) : null;
        if ($comment === '') {
            $comment = null;
        }

        $feedback = SystemFeedback::updateOrCreate(
            ['recipient_id' => $recipient->id],
            [
                'rating' => (int) $validated['rating'],
                'comment' => $comment,
            ]
        );

        UserLogService::log($recipient, 'Submitted system feedback');

        return response()->json([
            'message' => $feedback->wasRecentlyCreated
                ? 'System feedback submitted successfully.'
                : 'System feedback updated successfully.',
            'data' => $this->formatSystemFeedback($feedback),
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    // ============================================================
    // Formatters
    // ============================================================

    /** @return array<string, mixed> */
    private function formatAlertFeedback(AlertFeedback $f): array
    {
        return [
            'id' => $f->id,
            'alertId' => $f->alert_id,
            'recipientId' => $f->recipient_id,
            'rating' => $f->rating,
            'comment' => $f->comment ?? '',
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatOperatorFeedback(OperatorFeedback $f): array
    {
        return [
            'id' => $f->id,
            'recipientId' => $f->recipient_id,
            'operatorId' => $f->operator_id,
            'rating' => $f->rating,
            'comment' => $f->comment ?? '',
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatSystemFeedback(SystemFeedback $f): array
    {
        return [
            'id' => $f->id,
            'recipientId' => $f->recipient_id,
            'rating' => $f->rating,
            'comment' => $f->comment ?? '',
            'submittedAt' => $f->created_at?->toIso8601String(),
            'updatedAt' => $f->updated_at?->toIso8601String(),
        ];
    }
}
