<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token issuance for the future React/Electron/mobile clients. Deliberately
 * minimal — this is the auth foundation, not a full account-management API.
 */
class AuthController extends Controller
{
    public function login(Request $request, ActivityLogger $logger): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        // Soft-deleted users are excluded by the query above automatically
        // (default global scope) — they simply won't be found, same as a
        // wrong password, so no distinguishing information leaks either way.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your administrator.',
            ]);
        }

        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        $logger->log('login', $user, "{$user->name} logged in via API", actor: $user);

        return ApiResponse::success([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }

    public function logout(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

        $logger->log('logout', $user, "{$user->name} logged out via API", actor: $user);

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => $this->userPayload($request->user())], 'User retrieved successfully.');
    }

    /**
     * A deliberately explicit, hand-picked payload — never a raw model
     * dump — so adding a sensitive column to `users` later can't silently
     * leak it through this endpoint.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->is_super_admin,
            'status' => $user->status,
            'client_id' => $user->client_id,
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
        ];
    }
}
