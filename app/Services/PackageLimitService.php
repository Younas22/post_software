<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;

/**
 * Enforces the client's package limits (max_branches, max_users) — the
 * counterpart to DeviceActivationService's max_devices check, following the
 * exact same shape (limitFor/count/canCreate) so all three read the same
 * way. Nothing here duplicates DeviceActivationService; branches and users
 * are different tables with different counting rules, so each gets its own
 * method, but the *pattern* (null limit = unlimited, count < limit = ok) is
 * shared deliberately.
 */
class PackageLimitService
{
    /**
     * The client's current branch limit, or null if unlimited.
     */
    public function maxBranches(Client $client): ?int
    {
        return $client->currentSubscription?->package?->max_branches;
    }

    /**
     * Branches across ALL of this client's businesses — max_branches is a
     * client-wide (subscription-level) limit, not a per-business one, the
     * same way max_devices and max_users are client-wide.
     */
    public function branchCount(Client $client): int
    {
        return Branch::query()
            ->whereHas('business', fn ($query) => $query->where('client_id', $client->id))
            ->count();
    }

    public function canCreateBranch(Client $client): bool
    {
        $limit = $this->maxBranches($client);

        return $limit === null || $this->branchCount($client) < $limit;
    }

    /**
     * The client's current user (staff seat) limit, or null if unlimited.
     */
    public function maxUsers(Client $client): ?int
    {
        return $client->currentSubscription?->package?->max_users;
    }

    /**
     * What counts toward max_users, precisely: users with this client_id,
     * excluding soft-deleted (excluded automatically by Eloquent's default
     * global scope — a deleted user never needs to be filtered explicitly)
     * AND excluding status='inactive' (a deactivated employee frees their
     * seat immediately, exactly like an inactive device doesn't count
     * against max_devices). A user who has no business/branch assigned yet
     * still counts — the seat is consumed at the client level.
     */
    public function userCount(Client $client): int
    {
        return User::query()
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Pass $excluding when checking whether an existing user may be
     * (re)activated, so they don't count against their own seat.
     */
    public function canCreateUser(Client $client, ?User $excluding = null): bool
    {
        $limit = $this->maxUsers($client);

        if ($limit === null) {
            return true;
        }

        $query = User::query()->where('client_id', $client->id)->where('status', 'active');

        if ($excluding) {
            $query->whereKeyNot($excluding->id);
        }

        return $query->count() < $limit;
    }
}
