<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipient\LoginRecipientRequest;
use App\Http\Requests\Recipient\RegisterRecipientRequest;
use App\Http\Requests\Recipient\RequestAccessRequest;
use App\Http\Requests\Recipient\VerifyRecipientRequest;
use App\Models\Admin\Masterlist;
use App\Models\Recipient\AccessRequest;
use App\Models\Recipient\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class RecipientAuthController extends Controller
{
    /**
     * Step 1: Verify id_number + name against the masterlist.
     * Returns the matched masterlist data so the frontend can
     * expand the form and know whether to show student fields.
     */
    public function verify(VerifyRecipientRequest $request): JsonResponse
    {
        $masterlist = Masterlist::where('id_number', $request->id_number)
            ->whereRaw('LOWER(first_name) = ?', [strtolower(trim($request->first_name))])
            ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($request->last_name))])
            ->first();

        if (! $masterlist) {
            return response()->json([
                'verified' => false,
                'message' => 'We could not verify your information. Please check your ID number and name.',
            ], 422);
        }

        return response()->json([
            'verified' => true,
            'message' => 'Verification successful.',
            'data' => [
                'id_number' => $masterlist->id_number,
                'first_name' => $masterlist->first_name,
                'last_name' => $masterlist->last_name,
                'role' => $masterlist->role,
                'student_program' => $masterlist->student_program,
                'student_year' => $masterlist->student_year,
            ],
        ]);
    }

    /**
     * Step 2: Re-verify against the masterlist (never trust the client),
     * create the recipient account, then delete the masterlist row
     * since its job as a validation gate is complete.
     */
    public function register(RegisterRecipientRequest $request): JsonResponse
    {
        $masterlist = Masterlist::where('id_number', $request->id_number)
            ->whereRaw('LOWER(first_name) = ?', [strtolower(trim($request->first_name))])
            ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($request->last_name))])
            ->first();

        if (! $masterlist) {
            return response()->json([
                'message' => 'We could not verify your information. Please try again.',
            ], 422);
        }

        $recipient = DB::transaction(function () use ($masterlist, $request) {
            $recipient = Recipient::create([
                'id_number' => $masterlist->id_number,
                'first_name' => $masterlist->first_name,
                'last_name' => $masterlist->last_name,
                'role' => $masterlist->role,
                'student_program' => $masterlist->student_program,
                'student_year' => $masterlist->student_year,
                'contact_number' => $request->contact_number,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            $masterlist->delete();

            return $recipient;
        });

        return response()->json([
            'message' => 'Registration successful. You may now log in.',
            'data' => $recipient,
        ], 201);
    }

    /**
     * For recipients not found in the masterlist — submit a request
     * for an admin to review and manually add them.
     */
    public function requestAccess(RequestAccessRequest $request): JsonResponse
    {
        $accessRequest = AccessRequest::create([
            'id_number' => $request->id_number,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Your access request has been submitted. An admin will review it shortly.',
            'data' => $accessRequest,
        ], 201);
    }

    // ============================================================
    // AUTH / LOGIN - Authenticate recipient and issue token
    // ============================================================

    /**
     * Authenticate a recipient and issue a Sanctum token.
     * Implements rate limiting: 5 attempts per minute, keyed by
     * email + IP so it can't be used to lock out a specific account.
     */
    public function login(LoginRecipientRequest $request): JsonResponse
    {
        $throttleKey = 'recipient-login:'.strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $recipient = Recipient::where('email', $request->email)->first();

        // Always run Hash::check, even when no recipient is found, against a
        // dummy hash so the response time doesn't leak whether the email exists.
        $passwordValid = Hash::check(
            $request->password,
            $recipient->password ?? '$2y$10$invalidplaceholderhashvalueeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
        );

        if (! $recipient || ! $passwordValid) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($recipient->status === 'deactivated') {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact an administrator.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $token = $recipient->createToken('recipient-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'data' => [
                'id_number' => $recipient->id_number,
                'first_name' => $recipient->first_name,
                'last_name' => $recipient->last_name,
                'email' => $recipient->email,
                'role' => $recipient->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Recipient\Recipient $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
