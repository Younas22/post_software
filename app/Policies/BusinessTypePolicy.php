<?php

namespace App\Policies;

use App\Models\BusinessType;
use App\Models\User;

/**
 * Business Types are a platform-wide catalog (Configuration group), gated
 * by settings.* since Phase 2's permission list has no dedicated slug.
 */
class BusinessTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function view(User $user, BusinessType $businessType): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.update');
    }

    public function update(User $user, BusinessType $businessType): bool
    {
        return $user->hasPermission('settings.update');
    }
}
