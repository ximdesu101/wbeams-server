<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipient\StoreEmergencySosRequest;
use App\Http\Requests\Recipient\StoreReportRequest;
use App\Models\Recipient\Report;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * Build a public URL for a stored media path, or null.
     */
    private function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Shared JSON shape for a report (list + create responses).
     *
     * @return array<string, mixed>
     */
    private function formatReport(Report $report, ?\App\Models\Recipient\Recipient $sender = null): array
    {
        $sender = $sender ?? $report->recipient;

        return [
            'id' => $report->id,
            'title' => $report->title,
            'location' => $report->location,
            'urgency' => $report->urgency,
            'status' => $report->status,
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'profile' => $report->profile,
            'details' => $report->details,
            'created_at' => $report->created_at,
            'has_video' => $report->video_path !== null,
            'has_voice' => $report->voice_path !== null,
            'video_url' => $this->mediaUrl($report->video_path),
            'voice_url' => $this->mediaUrl($report->voice_path),
            'sender' => $sender ? [
                'id_number' => $sender->id_number,
                'first_name' => $sender->first_name,
                'last_name' => $sender->last_name,
                'role' => $sender->role,
                'email' => $sender->email,
            ] : null,
        ];
    }

    // ============================================================
    // 1. INDEX — List all reports submitted by the authenticated
    //    recipient, newest first, with media URLs included.
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $reports = Report::where('recipient_id', $recipient->id)
            ->with('recipient:id,id_number,first_name,last_name,role,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Report $report) => $this->formatReport($report));

        return response()->json(['data' => $reports]);
    }

    // ============================================================
    // 2. STORE — Submit a new report with optional video/voice.
    //    Files are stored in storage/app/public/reports/{type}.
    // ============================================================
    public function store(StoreReportRequest $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $videoPath = null;
        $voicePath = null;

        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('reports/videos', 'public');
        }

        if ($request->hasFile('voice')) {
            $voicePath = $request->file('voice')->store('reports/voices', 'public');
        }

        $report = Report::create([
            'recipient_id' => $recipient->id,
            'title' => $request->title,
            'location' => $request->location,
            'urgency' => $request->urgency,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'video_path' => $videoPath,
            'voice_path' => $voicePath,
            'status' => 'pending',
        ]);

        $report->setRelation('recipient', $recipient);

        SseNotifier::touch('reports');

        return response()->json([
            'message' => 'Report submitted successfully.',
            'data' => $this->formatReport($report, $recipient),
        ], 201);
    }

    // ============================================================
    // 3. DESTROY — Delete a report owned by the authenticated
    //    recipient, including stored video/voice files.
    // ============================================================
    public function destroy(Request $request, int $report): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $model = Report::where('id', $report)
            ->where('recipient_id', $recipient->id)
            ->first();

        if (!$model) {
            return response()->json([
                'message' => 'Report not found.',
            ], 404);
        }

        if ($model->video_path) {
            Storage::disk('public')->delete($model->video_path);
        }

        if ($model->voice_path) {
            Storage::disk('public')->delete($model->voice_path);
        }

        $model->delete();

        SseNotifier::touch('reports');

        return response()->json([
            'message' => 'Report deleted successfully.',
        ]);
    }

    public function emergencySos(StoreEmergencySosRequest $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $recipient */
        $recipient = $request->user();

        $report = Report::create([
            'recipient_id' => $recipient->id,
            'title' => 'Emergency SOS',
            'location' => $request->location,
            'urgency' => 'critical',
            'status' => 'pending',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'profile' => $request->profile,
            'details' => $request->details,
        ]);

        $report->setRelation('recipient', $recipient);

        SseNotifier::touch('reports');

        return response()->json([
            'message' => 'Emergency SOS submitted successfully.',
            'data' => $this->formatReport($report, $recipient),
        ], 201);
    }
}