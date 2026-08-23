<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

/**
 * Packages are a platform-wide SaaS catalog — Super Admin only, never
 * scoped to a tenant (there is no client_id on this model).
 */
class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('packages.view');
    }

    public function view(User $user, Package $package): bool
    {
        return $user->hasPermission('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('packages.create');
    }

    public function update(User $user, Package $package): bool
    {
        return $user->hasPermission('packages.update');
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->hasPermission('packages.delete');
    }
}
