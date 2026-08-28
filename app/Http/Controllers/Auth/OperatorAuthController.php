<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ActivateOperatorRequest;
use App\Http\Requests\Operator\ForgotPasswordRequest;
use App\Http\Requests\Operator\LoginOperatorRequest;
use App\Http\Requests\Operator\ResetPasswordRequest;
use App\Mail\OperatorPasswordResetMail;
use App\Models\Operator\Operator;
use App\Enums\OperatorStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OperatorAuthController extends Controller
{
    // ============================================================
    // 1. AUTH / VALIDATE - Check if activation token is valid
    // ============================================================
    
    /**
     * Validate an activation token for operator account setup
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateToken(Request $request): JsonResponse
    {
        $operator = $this->findByToken($request->query('token'));

        if (!$operator) {
            return $this->invalidTokenResponse();
        }

        if ($operator->is_token_expired) {
            $operator->markAsExpired();

            return $this->expiredTokenResponse($operator);
        }

        return response()->json([
            'message' => 'Token is valid.',
            'status' => 'valid',
            'data' => [
                'first_name' => $operator->first_name,
                'last_name' => $operator->last_name,
                'email' => $operator->email,
                'contact_number' => $operator->contact_number,
                'token_expires_at' => $operator->activation_token_expires_at,
                'expires_in_minutes' => now()->diffInMinutes($operator->activation_token_expires_at),
            ],
        ]);
    }

    // ============================================================
    // 2. AUTH / ACTIVATE - Activate operator account with password
    // ============================================================
    
    /**
     * Activate an operator account using a valid token
     * 
     * @param ActivateOperatorRequest $request
     * @return JsonResponse
     */
    public function activate(ActivateOperatorRequest $request): JsonResponse
    {
        $operator = $this->findByToken($request->token);

        if (!$operator) {
            return $this->invalidTokenResponse();
        }

        if ($operator->is_token_expired) {
            $operator->markAsExpired();

            return $this->expiredTokenResponse($operator);
        }

        $operator->update([
            'password' => $request->password,
            'status' => OperatorStatus::Active,
            'activation_token' => null,
            'activation_token_expires_at' => null,
            'activated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Account activated successfully. You can now log in.',
            'status' => 'activated',
            'data' => [
                'operator_id' => $operator->operator_id,
                'first_name' => $operator->first_name,
                'last_name' => $operator->last_name,
                'email' => $operator->email,
                'activated_at' => $operator->activated_at,
            ],
        ]);
    }

    // ============================================================
    // 3. AUTH / LOGIN - Authenticate operator and issue token
    // ============================================================
    
    /**
     * Authenticate an active operator and issue a Sanctum token
     * Implements rate limiting: 5 attempts per minute
     * 
     * @param LoginOperatorRequest $request
     * @return JsonResponse
     */
    public function login(LoginOperatorRequest $request): JsonResponse
    {
        $throttleKey = 'operator-login:' . strtolower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $operator = Operator::where('email', $request->email)
            ->where('status', OperatorStatus::Active)
            ->first();

        $passwordValid = Hash::check(
            $request->password,
            $operator->password ?? '$2y$10$invalidplaceholderhashvalueeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
        );

        if (!$operator || !$passwordValid) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $token = $operator->createToken('operator-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'operator' => $this->formatOperatorData($operator),
        ]);
    }

    // ============================================================
    // 4. AUTH / LOGOUT - Revoke current access token
    // ============================================================
    
    /**
     * Logout the authenticated operator by revoking their current token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Operator\Operator $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    // ============================================================
    // 5. AUTH / FORGOT PASSWORD - Send password reset link
    // ============================================================

    /**
     * Send a password reset link to an active operator.
     * Always returns a generic success message to avoid email enumeration.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $throttleKey = 'operator-forgot:' . strtolower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many reset attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $operator = Operator::where('email', $request->email)
            ->where('status', OperatorStatus::Active)
            ->first();

        if ($operator) {
            $plainToken = Str::random(64);

            $operator->update([
                'password_reset_token' => hash('sha256', $plainToken),
                'password_reset_token_expires_at' => now()->addMinutes(60),
            ]);

            $resetUrl = rtrim((string) config('app.frontend_url'), '/')
                . '/operator/reset-password?token=' . $plainToken;

            try {
                Mail::to($operator->email)->queue(
                    new OperatorPasswordResetMail(
                        $operator,
                        $resetUrl,
                        $operator->password_reset_token_expires_at->format('F j, Y g:i A')
                    )
                );
            } catch (\Throwable $e) {
                report($e);
                // Still return generic success to the client
            }
        }

        return response()->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
            'status' => 'sent',
        ]);
    }

    // ============================================================
    // 6. AUTH / VALIDATE RESET TOKEN
    // ============================================================

    public function validateResetToken(Request $request): JsonResponse
    {
        $operator = $this->findByResetToken($request->query('token'));

        if (!$operator) {
            return response()->json([
                'message' => 'Invalid or already used reset link.',
                'status' => 'invalid',
            ], 404);
        }

        if (
            $operator->password_reset_token_expires_at === null
            || now()->isAfter($operator->password_reset_token_expires_at)
        ) {
            $operator->update([
                'password_reset_token' => null,
                'password_reset_token_expires_at' => null,
            ]);

            return response()->json([
                'message' => 'This reset link has expired. Please request a new one.',
                'status' => 'expired',
            ], 410);
        }

        return response()->json([
            'message' => 'Token is valid.',
            'status' => 'valid',
            'data' => [
                'email' => $operator->email,
                'first_name' => $operator->first_name,
                'token_expires_at' => $operator->password_reset_token_expires_at,
            ],
        ]);
    }

    // ============================================================
    // 7. AUTH / RESET PASSWORD
    // ============================================================

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $operator = $this->findByResetToken($request->token);

        if (!$operator) {
            return response()->json([
                'message' => 'Invalid or already used reset link.',
                'status' => 'invalid',
            ], 404);
        }

        if (
            $operator->password_reset_token_expires_at === null
            || now()->isAfter($operator->password_reset_token_expires_at)
        ) {
            $operator->update([
                'password_reset_token' => null,
                'password_reset_token_expires_at' => null,
            ]);

            return response()->json([
                'message' => 'This reset link has expired. Please request a new one.',
                'status' => 'expired',
            ], 410);
        }

        $operator->update([
            'password' => $request->password,
            'password_reset_token' => null,
            'password_reset_token_expires_at' => null,
        ]);

        // Revoke existing sessions so old tokens stop working
        $operator->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now log in with your new password.',
            'status' => 'reset',
        ]);
    }

    // ============================================================
    // 8. AUTH / ME - Get authenticated operator profile
    // ============================================================
    
    /**
     * Get the currently authenticated operator
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'operator' => $this->formatOperatorData(($u = $request->user()) instanceof Operator ? $u : throw new \LogicException("Expected Operator")),
        ]);
    }

    // ============================================================
    // 6. HELPER METHODS - Supporting functions
    // ============================================================
    
    /**
     * Find an operator by activation token
     * 
     * @param string|null $token
     * @return Operator|null
     */
    protected function findByToken(?string $token): ?Operator
    {
        if (!$token) {
            return null;
        }

        return Operator::where('activation_token', hash('sha256', $token))
            ->where('status', OperatorStatus::Inactive)
            ->first();
    }

    /**
     * Find an active operator by password reset token
     */
    protected function findByResetToken(?string $token): ?Operator
    {
        if (!$token) {
            return null;
        }

        return Operator::where('password_reset_token', hash('sha256', $token))
            ->where('status', OperatorStatus::Active)
            ->first();
    }

    /**
     * Return response for invalid or already used token
     * 
     * @return JsonResponse
     */
    protected function invalidTokenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid or already used activation link.',
            'status' => 'invalid',
        ], 404);
    }

    /**
     * Return response for expired token
     * 
     * @param Operator $operator
     * @return JsonResponse
     */
    protected function expiredTokenResponse(Operator $operator): JsonResponse
    {
        return response()->json([
            'message' => 'Activation link has expired. Please request a new invitation.',
            'status' => 'expired',
            'operator_id' => $operator->operator_id,
        ], 410);
    }

    /**
     * Format operator data for API responses
     * 
     * @param Operator $operator
     * @return array
     */
    /**
     * @return array<string, mixed>
     */
    protected function formatOperatorData(Operator $operator): array
    {
        return [
            'operator_id' => $operator->operator_id,
            'first_name' => $operator->first_name,
            'last_name' => $operator->last_name,
            'full_name' => $operator->full_name,
            'email' => $operator->email,
            'contact_number' => $operator->contact_number,
            'status' => $operator->status,
            'status_label' => $operator->status_label,
            'activated_at' => $operator->activated_at,
        ];
    }
}