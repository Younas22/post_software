<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\Branch;
use App\Models\Device;
use App\Services\DeviceActivationService;
use App\Services\SubscriptionEnforcementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Device registration is deliberately performed by an already-authenticated
 * privileged USER (the admin setting up a new terminal), not by Electron
 * acting anonymously — this is what lets the server validate business,
 * subscription, and device-limit state before ever issuing a credential,
 * and is what "never trust client-supplied device identity" means in
 * practice: nothing about the device's identity comes from the request
 * except a human-chosen label and a target branch.
 *
 * Activation/deactivation/blocking of an already-registered device remains
 * on the existing Admin panel (Admin\DeviceController, session-authenticated)
 * — not duplicated here.
 */
class DeviceController extends Controller
{
    public function register(
        RegisterDeviceRequest $request,
        DeviceActivationService $devices,
        SubscriptionEnforcementService $enforcement,
    ): JsonResponse {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $client = $branch->business->client;

        if (! $enforcement->hasPlatformAccess($client)) {
            return ApiResponse::error("This client's subscription does not allow platform access.", null, 403);
        }

        if (! $devices->canActivate($client)) {
            $limit = $devices->limitFor($client);

            return ApiResponse::error(
                "This client's package allows at most {$limit} active device(s). Deactivate a device or upgrade the package.",
                null,
                403,
            );
        }

        $device = new Device();
        $device->client_id = $client->id;
        $device->business_id = $branch->business_id;
        $device->branch_id = $branch->id;
        $device->fill([
            'name' => $request->string('name'),
            'platform' => $request->string('platform'),
            // Server-generated, never accepted from the request — this is
            // the device's identity, not a caller-supplied claim.
            'device_identifier' => (string) Str::uuid(),
            'status' => 'active',
            'activated_at' => now(),
        ]);
        $device->save();

        $token = $device->createToken($device->name)->plainTextToken;

        return ApiResponse::success([
            'device' => $this->payload($device),
            // Shown exactly once, like any Sanctum personal access token —
            // Electron must store this now; it cannot be retrieved again.
            'token' => $token,
        ], 'Device registered successfully.', 201);
    }

    /**
     * Device-authenticated (see the 'device.active' middleware) — lets
     * Electron verify its stored credential is still valid and current.
     */
    public function current(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        return ApiResponse::success($this->payload($device), 'Device retrieved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Device $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'platform' => $device->platform,
            'device_identifier' => $device->device_identifier,
            'status' => $device->status,
            'client_id' => $device->client_id,
            'business_id' => $device->business_id,
            'branch_id' => $device->branch_id,
            'activated_at' => $device->activated_at,
            'last_seen_at' => $device->last_seen_at,
        ];
    }
}
