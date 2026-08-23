<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Clients (SaaS tenants) are managed exclusively by Super Admin — no
 * client-side role ever reaches these abilities, but the policy still
 * enforces the underlying `clients.*` permissions explicitly rather than
 * relying on the `super-admin` route middleware alone.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.delete');
    }
}
