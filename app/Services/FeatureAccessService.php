<?php

namespace App\Services;

use App\Models\Business;

/**
 * The single place that decides whether a business actually has a given
 * feature enabled, so every future module (POS, Inventory, Multi Branch...)
 * enforces feature flags the same way instead of re-deriving this logic.
 *
 * Resolution order:
 *   1. An explicit business-level override (business_feature row) always
 *      wins, in either direction (can enable something the package doesn't
 *      grant, or disable something it does).
 *   2. Otherwise, whether the client's current subscription's package
 *      includes the feature.
 *   3. Otherwise false (no override, no package, or no matching feature).
 */
class FeatureAccessService
{
    public function isEnabled(Business $business, string $featureSlug): bool
    {
        $override = $business->featureOverrides()
            ->where('slug', $featureSlug)
            ->first();

        if ($override !== null) {
            return (bool) $override->pivot->enabled;
        }

        $package = $business->client?->currentSubscription?->package;

        if ($package === null) {
            return false;
        }

        return $package->features()->where('slug', $featureSlug)->exists();
    }
}
