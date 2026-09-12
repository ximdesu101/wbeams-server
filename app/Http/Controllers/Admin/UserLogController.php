<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UserLog::orderByDesc('logged_at');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'LIKE', "%{$search}%")
                    ->orWhere('actor_email', 'LIKE', "%{$search}%")
                    ->orWhere('actor_contact', 'LIKE', "%{$search}%")
                    ->orWhere('activity', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('activity') && $request->activity !== 'all') {
            $query->where('activity', $request->activity);
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('actor_role', ucfirst($request->role));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $logs = $query->paginate($perPage);

        $mapped = $logs->getCollection()->map(fn (UserLog $log) => [
            'id' => $log->id,
            'name' => $log->actor_name,
            'email' => $log->actor_email,
            'contact' => $log->actor_contact ?? '—',
            'role' => $log->actor_role,
            'activity' => $log->activity,
            'time' => $log->logged_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $mapped,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
