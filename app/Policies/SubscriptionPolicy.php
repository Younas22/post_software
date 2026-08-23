<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * Tenant isolation for Subscription. Client-side users may only view their
 * own client's subscription (subscriptions.view) — platform-wide management
 * (subscriptions.manage) is reserved for Super Admin, who is never assigned
 * that permission on the client side (see RolePermissionSeeder).
 */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('subscriptions.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('subscriptions.view') && $subscription->client_id === $user->client_id);
    }

    public function manage(User $user, Subscription $subscription): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('subscriptions.manage') && $subscription->client_id === $user->client_id);
    }
}
