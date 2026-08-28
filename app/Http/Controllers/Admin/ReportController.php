<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recipient\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Report::with([
                'recipient:id,first_name,last_name,role,email',
                'handledByOperator:id,first_name,last_name,operator_id',
            ])
            ->orderByDesc('created_at');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('recipient', function ($rq) use ($search) {
                        $rq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('handledByOperator', function ($oq) use ($search) {
                        $oq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('operator_id', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $reports = $query->paginate($perPage);

        $mapped = $reports->getCollection()->map(function (\App\Models\Recipient\Report $report) {
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
            ];
        });

        return response()->json([
            'data' => $mapped,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
                'per_page'     => $reports->perPage(),
                'total'        => $reports->total(),
            ],
        ]);
    }

    /**
     * Lightweight counts – never loads the full table.
     */
    public function stats(): JsonResponse
    {
        $total = Report::count();

        $byStatus = Report::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'total'    => $total,
                'pending'  => (int) ($byStatus['pending'] ?? 0),
                'resolved' => (int) ($byStatus['resolved'] ?? 0),
                'rejected' => (int) ($byStatus['rejected'] ?? 0),
                'acknowledged' => (int) ($byStatus['acknowledged'] ?? 0),
            ],
        ]);
    }
}