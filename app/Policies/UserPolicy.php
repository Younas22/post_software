<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs employee (staff) management via /api/v1/employees. $target here
 * is the employee being viewed/managed — never confuse it with $user, the
 * authenticated actor. Tenant isolation is the same client_id comparison
 * pattern as every other policy; Super Admin bypasses everywhere, matching
 * their platform-wide role.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('users.view') && $target->client_id === $user->client_id);
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('users.update') && $target->client_id === $user->client_id);
    }
}
