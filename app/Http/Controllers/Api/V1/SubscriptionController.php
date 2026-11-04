<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeviceActivationService;
use App\Services\PackageLimitService;
use App\Support\ApiResponse;
use App\Support\PlatformCurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client-side subscription visibility — previously absent entirely (see
 * docs/LARAVEL_API_DISCOVERY.md §21): a logged-in client user had no way to
 * see their own plan/limits/usage. All logic reused as-is from the services
 * that already enforce these limits server-side (PackageLimitService,
 * DeviceActivationService, Subscription::effectiveStatus()) — this
 * controller only reads and presents it.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, PackageLimitService $packageLimits, DeviceActivationService $deviceLimits): JsonResponse
    {
        $user = $request->user();
        $client = $user->client;

        if (! $client) {
            return ApiResponse::error('No client is associated with this account.', null, 404);
        }

        $subscription = $client->currentSubscription;
        $package = $subscription?->package;

        return ApiResponse::success([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'status' => $client->status,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'effective_status' => $subscription->effectiveStatus(),
                'trial_ends_at' => $subscription->trial_ends_at,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'cancelled_at' => $subscription->cancelled_at,
            ] : null,
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'price' => $package->price,
                // Package prices are in the platform's default currency (Super Admin settings).
                'currency' => app(PlatformCurrency::class)->code(),
                'billing_cycle' => $package->billing_cycle,
                'max_businesses' => $package->max_businesses,
                'max_branches' => $package->max_branches,
                'max_users' => $package->max_users,
                'max_devices' => $package->max_devices,
                'features' => $package->features()->where('status', 'active')->pluck('slug'),
            ] : null,
            'usage' => [
                'users' => [
                    'used' => $packageLimits->userCount($client),
                    'limit' => $packageLimits->maxUsers($client),
                ],
                'branches' => [
                    'used' => $packageLimits->branchCount($client),
                    'limit' => $packageLimits->maxBranches($client),
                ],
                'devices' => [
                    'used' => $deviceLimits->activeCount($client),
                    'limit' => $deviceLimits->limitFor($client),
                ],
            ],
        ], 'Subscription retrieved successfully.');
    }
}
