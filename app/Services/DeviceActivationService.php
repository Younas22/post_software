<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Device;

/**
 * Centralizes the "can this client activate another device?" rule against
 * their package's `max_devices` limit, so no controller/view needs its own
 * copy of the counting logic.
 */
class DeviceActivationService
{
    /**
     * The client's current device limit, or null if unlimited (no
     * subscription/package, or the package itself has no cap).
     */
    public function limitFor(Client $client): ?int
    {
        return $client->currentSubscription?->package?->max_devices;
    }

    /**
     * How many of the client's devices currently hold `active` status.
     */
    public function activeCount(Client $client): int
    {
        return $client->devices()->where('status', 'active')->count();
    }

    /**
     * Whether one more device may be activated for this client right now.
     * Pass $excluding when re-activating a device that might itself already
     * be counted (so it doesn't count against its own limit).
     */
    public function canActivate(Client $client, ?Device $excluding = null): bool
    {
        $limit = $this->limitFor($client);

        if ($limit === null) {
            return true;
        }

        $query = $client->devices()->where('status', 'active');

        if ($excluding) {
            $query->whereKeyNot($excluding->id);
        }

        return $query->count() < $limit;
    }
}
