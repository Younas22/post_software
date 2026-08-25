<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Feature;
use App\Services\FeatureAccessService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bootstrap configuration for a client app (React/Electron/PWA) once it has
 * a token: this business's resolved settings and which platform features
 * are actually enabled for it. Deliberately scoped to "my own business" only
 * — nothing here is parameterized by an id from the request, so there is no
 * cross-tenant id to tamper with in the first place.
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings, FeatureAccessService $features): JsonResponse
    {
        $user = $request->user();
        // Falls back to the client's sole business when the user has no
        // fixed business_id but only one exists — a Client Owner is not
        // pinned to a business_id by design (they may run several), but
        // most only ever have one, and without this fallback they'd get no
        // config/settings/features at all. See User::defaultBusinessId().
        $businessId = $user->defaultBusinessId();
        $business = $businessId ? Business::query()->find($businessId) : null;

        if (! $business) {
            // Super Admin, a user with no business yet, or a Client Owner
            // whose client has multiple businesses (genuinely ambiguous,
            // no business picker exists yet) — only system-wide
            // configuration is meaningful.
            return ApiResponse::success([
                'business' => null,
                'settings' => collect($settings->keysForScope('system'))
                    ->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key)]),
                'features' => [],
            ], 'Configuration retrieved successfully.');
        }

        $businessSettings = collect($settings->keysForScope('business'))
            ->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key, business: $business)]);

        $enabledFeatures = Feature::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (Feature $feature) => $features->isEnabled($business, $feature->slug))
            ->pluck('slug')
            ->values();

        return ApiResponse::success([
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'currency' => $business->currency,
                'timezone' => $business->timezone,
            ],
            // The caller's OWN branch, permission-free — a Cashier is
            // pinned to exactly one branch (User::branch_id) but has no
            // `businesses.view` permission, so GET /branches (which
            // requires it) 403s for them. Every branch-scoped screen
            // (POS, Purchases, Expenses, ...) needs at least this much
            // just to know their own branch's name — found missing via
            // live testing as a Cashier, not assumed.
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'is_main' => $user->branch->is_main,
            ] : null,
            'settings' => $businessSettings,
            'features' => $enabledFeatures,
        ], 'Configuration retrieved successfully.');
    }
}
