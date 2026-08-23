<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Self-service account actions for the currently authenticated user — split
 * out from AuthController (token issuance) deliberately, per that
 * controller's own "not a full account-management API" scope note.
 */
class AccountController extends Controller
{
    public function updatePassword(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        $logger->log('user.password_changed', $user, "{$user->name} changed their password", actor: $user);

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->fill($data);
        $user->save();

        return ApiResponse::success($user->only(['id', 'name', 'email']), 'Profile updated successfully.');
    }
}
