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
use App\Services\UserLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class RecipientAuthController extends Controller
{
    public function sendRegistrationCode(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower($validated['email']);
        $throttleKey = 'recipient-registration-otp-send:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json(['message' => 'Too many code requests. Please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        if (Recipient::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'That email address is already registered.'], 422);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put('recipient-registration-otp:'.$email, Hash::make($code), now()->addMinutes(10));
        Mail::raw("Your email verification code is {$code}. It expires in 10 minutes.", function ($message) use ($email) {
            $message->to($email)->subject('Verify your recipient account email');
        });

        return response()->json(['message' => 'Verification code sent. Check your email inbox.']);
    }

    public function verifyRegistrationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = strtolower($validated['email']);
        $throttleKey = 'recipient-registration-otp-verify:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'Too many verification attempts. Please request a new code.'], 429);
        }
        RateLimiter::hit($throttleKey, 600);

        $storedHash = Cache::get('recipient-registration-otp:'.$email);
        if (! $storedHash || ! Hash::check($validated['code'], $storedHash)) {
            return response()->json(['message' => 'The verification code is invalid or expired. Please request a new code.'], 422);
        }

        RateLimiter::clear($throttleKey);

        return response()->json(['verified' => true, 'message' => 'Email verified.']);
    }

    public function sendPasswordResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower($validated['email']);
        $throttleKey = 'recipient-password-reset-otp-send:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json(['message' => 'Too many reset requests. Please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $recipient = Recipient::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($recipient) {
            $code = (string) random_int(100000, 999999);
            Cache::put('recipient-password-reset-otp:'.$email, Hash::make($code), now()->addMinutes(10));
            Mail::raw("Your password reset code is {$code}. It expires in 10 minutes.", function ($message) use ($email) {
                $message->to($email)->subject('Reset your recipient account password');
            });
        }

        return response()->json([
            'message' => 'If an account with that email exists, a password reset code has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $email = strtolower($validated['email']);
        $throttleKey = 'recipient-password-reset-otp-verify:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'Too many reset attempts. Please request a new code.'], 429);
        }
        RateLimiter::hit($throttleKey, 600);

        $otpKey = 'recipient-password-reset-otp:'.$email;
        $storedHash = Cache::get($otpKey);
        $recipient = Recipient::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $recipient || ! $storedHash || ! Hash::check($validated['code'], $storedHash)) {
            return response()->json(['message' => 'The reset code is invalid or expired. Please request a new code.'], 422);
        }

        $recipient->update(['password' => $validated['password']]);
        $recipient->tokens()->delete();
        Cache::forget($otpKey);
        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Password reset successfully. You can now log in with your new password.',
        ]);
    }

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
        $email = strtolower($request->email);
        $otpKey = 'recipient-registration-otp:'.$email;
        $otpHash = Cache::get($otpKey);
        if (! $otpHash || ! Hash::check($request->email_code, $otpHash)) {
            return response()->json([
                'message' => 'Please verify your email with a valid, unexpired code before registering.',
            ], 422);
        }

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

        Cache::forget($otpKey);

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

        UserLogService::log($recipient, 'Logged in');

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
        /** @var Recipient $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        UserLogService::log($user, 'Logged out');

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
