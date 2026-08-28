<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recipient\Recipient;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientController extends Controller
{
    // ============================================================
    // 1. READ / LIST - Display all recipients with filters and role counts
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $query = Recipient::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'deactivated'], true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $recipients = $query->latest()->paginate(20);

        $roleCounts = Recipient::selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        $statusCounts = Recipient::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => $recipients->items(),
            'meta' => [
                'current_page' => $recipients->currentPage(),
                'last_page' => $recipients->lastPage(),
                'total' => $recipients->total(),
                'total_registered' => Recipient::count(),
                'role_counts' => [
                    'student' => $roleCounts->get('student', 0),
                    'faculty' => $roleCounts->get('faculty', 0),
                    'staff' => $roleCounts->get('staff', 0),
                ],
                'status_counts' => [
                    'active' => (int) $statusCounts->get('active', 0),
                    'deactivated' => (int) $statusCounts->get('deactivated', 0),
                ],
            ],
        ]);
    }

    // ============================================================
    // 2. READ / SHOW - Display a single recipient
    // ============================================================
    public function show(Recipient $recipient): JsonResponse
    {
        return response()->json([
            'data' => $recipient,
        ]);
    }

    // ============================================================
    // 3. UPDATE STATUS - Activate / deactivate recipient account
    // ============================================================
    public function updateStatus(Request $request, Recipient $recipient): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,deactivated'],
        ]);

        $status = $validated['status'];

        if ($status === 'deactivated') {
            if ($recipient->status !== 'active') {
                return response()->json([
                    'message' => 'Only an active account can be deactivated.',
                ], 422);
            }

            $recipient->update(['status' => 'deactivated']);

            // Revoke all active tokens so the user is logged out immediately
            $recipient->tokens()->delete();
        } else {
            if ($recipient->status !== 'deactivated') {
                return response()->json([
                    'message' => 'Only a deactivated account can be reactivated.',
                ], 422);
            }

            $recipient->update(['status' => 'active']);
        }

        SseNotifier::touch('recipients');

        return response()->json([
            'message' => 'Recipient account status updated successfully.',
            'data' => $recipient->refresh(),
        ]);
    }

    // ============================================================
    // 4. DELETE - Permanently remove recipient account
    // ============================================================
    public function destroy(Recipient $recipient): JsonResponse
    {
        $recipient->tokens()->delete();
        $recipient->delete();

        SseNotifier::touch('recipients');

        return response()->json([
            'message' => 'Recipient account deleted successfully.',
        ]);
    }
}
