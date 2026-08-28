<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\Admin\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    // ============================================================
    // 1. AUTH / LOGIN - Authenticate admin and issue token
    // ============================================================
    
    /**
     * Authenticate an admin user and issue a Sanctum token
     * Implements rate limiting: 5 attempts per minute
     * 
     * @param AdminLoginRequest $request
     * @return JsonResponse
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $throttleKey = 'admin-login:' . strtolower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $admin = Admin::where('email', $request->email)->first();

        $passwordValid = Hash::check(
            $request->password,
            $admin->password ?? '$2y$10$invalidplaceholderhashvalueeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
        );

        if (!$admin || !$passwordValid) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'admin' => [
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
            ],
        ]);
    }

    // ============================================================
    // 2. AUTH / ME - Get authenticated admin profile
    // ============================================================
    
    /**
     * Get the currently authenticated admin user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'admin' => $request->user(),
        ]);
    }

    // ============================================================
    // 3. AUTH / LOGOUT - Revoke current access token
    // ============================================================
    
    /**
     * Logout the authenticated admin by revoking their current token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Admin\Admin $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}