<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * System-wide settings (Configuration group) are gated by settings.* —
 * business/branch-scoped settings are authorized via BusinessPolicy /
 * BranchPolicy instead (see BusinessController::updateSettings), since
 * those already carry the tenant-isolation check a bare Setting row can't
 * express on its own.
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->hasPermission('settings.update');
    }
}
