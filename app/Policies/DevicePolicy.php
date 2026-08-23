<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * Tenant isolation for Device — mirrors BusinessPolicy's pattern against
 * the device's denormalized client_id.
 */
class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('devices.view');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('devices.view') && $device->client_id === $user->client_id);
    }

    public function manage(User $user, Device $device): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('devices.manage') && $device->client_id === $user->client_id);
    }
}
