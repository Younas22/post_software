<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * Tenant isolation for Business: a client-side user may only see/manage
 * businesses under their own client_id, never another client's — no matter
 * what id is present in the URL/form. Super Admins bypass every check.
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('businesses.view');
    }

    public function view(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('businesses.view') && $business->client_id === $user->client_id);
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('businesses.create');
    }

    public function update(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('businesses.update') && $business->client_id === $user->client_id);
    }
}
