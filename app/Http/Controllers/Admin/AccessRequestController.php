<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccessRequestApprovedMail;
use App\Mail\AccessRequestRejectedMail;
use App\Models\Admin\Masterlist;
use App\Models\Recipient\AccessRequest;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AccessRequestController extends Controller
{
    // ============================================================
    // 1. READ / LIST - Display all access requests with filters
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $query = AccessRequest::query();

        if ($request->filled('status')) {
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

        $requests = $query->latest()->paginate(20);

        return response()->json([
            'data' => $requests->items(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
                'pending_count' => AccessRequest::where('status', 'pending')->count(),
            ],
        ]);
    }

    // ============================================================
    // 2. READ / SHOW - Get specific statistics (pending count)
    // ============================================================
    public function pendingCount(): JsonResponse
    {
        return response()->json([
            'pending_count' => AccessRequest::where('status', 'pending')->count(),
        ]);
    }

    // ============================================================
    // 3. UPDATE - Approve an access request and add to masterlist
    // ============================================================
    public function approve(Request $request, AccessRequest $accessRequest): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['student', 'faculty', 'staff'])],
            'student_program' => [
                'nullable',
                'required_if:role,student',
                Rule::in(['BSIT', 'BSCrim', 'BEED', 'BTLED', 'BSABE', 'BSA', 'BSF', 'BAT']),
            ],
            'student_year' => [
                'nullable',
                'required_if:role,student',
                Rule::in(['1st year', '2nd year', '3rd year', '4th year']),
            ],
        ]);

        DB::transaction(function () use ($accessRequest, $validated) {
            Masterlist::create([
                'id_number' => $accessRequest->id_number,
                'first_name' => $accessRequest->first_name,
                'last_name' => $accessRequest->last_name,
                'role' => $validated['role'],
                'student_program' => $validated['role'] === 'student' ? $validated['student_program'] : null,
                'student_year' => $validated['role'] === 'student' ? $validated['student_year'] : null,
            ]);

            $accessRequest->update(['status' => 'approved']);
        });

        // Send approval email to the recipient
        try {
            Mail::to($accessRequest->email)->queue(
                new AccessRequestApprovedMail($accessRequest)
            );
        } catch (\Throwable $e) {
            report($e);
            // Do not fail the approval if email fails
        }

        SseNotifier::touch('access-requests');
        SseNotifier::touch('masterlist');

        return response()->json([
            'message' => 'Access request approved and added to the masterlist. Notification email sent.',
            'data' => $accessRequest,
        ]);
    }

    // ============================================================
    // 4. UPDATE - Reject an access request
    // ============================================================
    public function reject(AccessRequest $accessRequest): JsonResponse
    {
        $accessRequest->update(['status' => 'rejected']);

        // Send rejection email to the recipient
        try {
            Mail::to($accessRequest->email)->queue(
                new AccessRequestRejectedMail($accessRequest)
            );
        } catch (\Throwable $e) {
            report($e);
            // Do not fail the rejection if email fails
        }

        SseNotifier::touch('access-requests');

        return response()->json([
            'message' => 'Access request rejected. Notification email sent.',
            'data' => $accessRequest,
        ]);
    }
}