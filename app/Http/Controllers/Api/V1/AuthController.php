<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
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
     * JSON wrapper around Laravel's own Password broker — identical
     * verification/hashing/expiry/single-use guarantees as the session-based
     * Blade flow (Auth\PasswordResetLinkController), just returning
     * ApiResponse instead of a redirect. Deliberately does not reveal
     * whether the email exists: any $status other than RESET_LINK_SENT is
     * reported as a generic validation error the same way the Blade
     * controller does via trans($status).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return ApiResponse::success(null, trans($status));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return ApiResponse::success(null, trans($status));
    }

    /**
     * A deliberately explicit, hand-picked payload — never a raw model
     * dump — so adding a sensitive column to `users` later can't silently
     * leak it through this endpoint.
     *
     * `roles`/`permissions` are included so the frontend can gate
     * navigation/UI without a second round-trip — this is display-only
     * convenience, never the authorization boundary: Laravel re-checks
     * every permission server-side on every request regardless of what a
     * client caches from here.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'is_super_admin' => $user->is_super_admin,
            'status' => $user->status,
            'client_id' => $user->client_id,
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'roles' => $user->roles->pluck('slug')->values(),
            'permissions' => $user->roles->pluck('permissions')->flatten()->pluck('slug')->unique()->values(),
        ];
    }
}
