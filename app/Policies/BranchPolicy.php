<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

/**
 * Tenant isolation for Branch, via its parent Business's client_id — mirrors
 * BusinessPolicy's pattern.
 */
class BranchPolicy
{
    use ScopesToBranch;

    public function view(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('businesses.view') && $branch->business->client_id === $user->client_id);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('businesses.update') && $business->client_id === $user->client_id);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('businesses.update') && $branch->business->client_id === $user->client_id);
    }

    /**
     * Ability for actions performed "at" a branch rather than on the Branch
     * record itself — e.g. adjusting stock (see InventoryController). Laravel
     * resolves a policy by the class of the argument passed to authorize(),
     * so any 'adjust'-style branch-scoped ability belongs here, not on
     * InventoryPolicy, even though inventory.adjust is the permission slug.
     */
    public function adjust(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('inventory.adjust') && $this->actorMayAccessBranch($user, $branch));
    }
}
