<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every device-scoped route: the presented Sanctum token must belong
 * to a Device (not a User — those are separate tokenables sharing the same
 * guard, see Device::class docblock), and that device's status must be
 * 'active'. A blocked or deactivated device's token is still technically
 * "valid" to Sanctum (it hasn't been revoked) — this is what actually stops
 * it from reaching a protected device API, independent of revocation.
 */
class EnsureDeviceIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (! $device instanceof Device) {
            return ApiResponse::error('This endpoint requires a device credential.', null, 401);
        }

        if ($device->status !== 'active') {
            return ApiResponse::error('This device is not active. Contact your administrator.', null, 403);
        }

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }
}
