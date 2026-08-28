<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Recipient\Report;
use Illuminate\Http\JsonResponse;
use App\Support\SseNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    private function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function index(Request $request): JsonResponse
    {
        $reports = Report::with([
                'recipient:id,first_name,last_name,role,email',
                'handledByOperator:id,first_name,last_name,operator_id',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Report $report) {
                $operator = $report->handledByOperator;

                return [
                    'id'               => $report->id,
                    'EmergencyType'    => $report->title ?? '',
                    'Description'      => $report->details ?? $report->title ?? '',
                    'location'         => $report->location ?? '',
                    'latitude'         => $report->latitude,
                    'longitude'        => $report->longitude,
                    'profile'          => $report->profile,
                    'ReportedBy'       => trim(($report->recipient->first_name ?? '') . ' ' . ($report->recipient->last_name ?? '')),
                    'DateReported'     => $report->created_at->toDateString(),
                    'status'           => $report->status ?? '',
                    'AssignedOperator' => $operator
                        ? trim(($operator->first_name ?? '') . ' ' . ($operator->last_name ?? ''))
                        : '',
                    'ReviewAt'         => $report->status_updated_at?->format('Y-m-d H:i') ?? '',
                    'has_video'        => $report->video_path !== null,
                    'has_voice'        => $report->voice_path !== null,
                    'video_url'        => $this->mediaUrl($report->video_path),
                    'voice_url'        => $this->mediaUrl($report->voice_path),
                ];
            });

        return response()->json(['data' => $reports]);
    }

    public function updateStatus(Request $request, int $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,acknowledged,rejected,resolved'],
        ]);

        $model = Report::find($report);

        if (!$model) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $operator = $request->user();

        $model->update([
            'status'                 => $validated['status'],
            'handled_by_operator_id' => $operator?->id,
            'status_updated_at'      => now(),
        ]);

        $model->load('handledByOperator:id,first_name,last_name,operator_id');

        $op = $model->handledByOperator;

        SseNotifier::touch('reports');

        return response()->json([
            'message' => 'Report status updated successfully.',
            'data' => [
                'id'               => $model->id,
                'status'           => $model->status,
                'AssignedOperator' => $op
                    ? trim(($op->first_name ?? '') . ' ' . ($op->last_name ?? ''))
                    : '',
                'ReviewAt'         => $model->status_updated_at?->format('Y-m-d H:i') ?? '',
            ],
        ]);
    }
}