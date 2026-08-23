<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Client;

/**
 * The single place that answers "is this client/business actually allowed
 * to use the platform right now" — every controller/middleware that needs
 * one of these checks calls in here instead of re-deriving it, so a future
 * change to what "active" means (e.g. a grace period) happens in one place.
 *
 * This composes FeatureAccessService and DeviceActivationService rather
 * than re-implementing their logic — this service is the single entry
 * point for "can this tenant do X", not a competing source of truth.
 */
class SubscriptionEnforcementService
{
    public function __construct(
        private readonly FeatureAccessService $features,
        private readonly DeviceActivationService $devices,
        private readonly PackageLimitService $limits,
    ) {
    }

    public function isClientActive(Client $client): bool
    {
        return $client->status === 'active';
    }

    public function isSubscriptionActive(Client $client): bool
    {
        $subscription = $client->currentSubscription;

        return $subscription !== null && in_array($subscription->effectiveStatus(), ['active', 'trial'], true);
    }

    public function hasSubscriptionExpired(Client $client): bool
    {
        return $client->currentSubscription?->effectiveStatus() === 'expired';
    }

    /**
     * Whether the client is in good enough standing to use the platform at
     * all: an active client account with an active (or trial) subscription.
     * This is the one call site a route middleware should actually gate on.
     */
    public function hasPlatformAccess(Client $client): bool
    {
        return $this->isClientActive($client) && $this->isSubscriptionActive($client);
    }

    public function isFeatureAvailable(Business $business, string $featureSlug): bool
    {
        return $this->features->isEnabled($business, $featureSlug);
    }

    public function isDeviceLimitReached(Client $client): bool
    {
        return ! $this->devices->canActivate($client);
    }

    public function isBranchLimitReached(Client $client): bool
    {
        return ! $this->limits->canCreateBranch($client);
    }

    public function isUserLimitReached(Client $client): bool
    {
        return ! $this->limits->canCreateUser($client);
    }
}
