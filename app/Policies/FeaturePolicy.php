<?php

namespace App\Policies;

use App\Models\Feature;
use App\Models\User;

/**
 * Features are a platform-wide catalog, gated by packages.* (see
 * RolePermissionSeeder / routes/admin.php — Features has no dedicated
 * permission slug in the Phase 2 list).
 */
class FeaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('packages.view');
    }

    public function view(User $user, Feature $feature): bool
    {
        return $user->hasPermission('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('packages.create');
    }

    public function update(User $user, Feature $feature): bool
    {
        return $user->hasPermission('packages.update');
    }
}
