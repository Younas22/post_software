<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

class InventoryPolicy
{
    use ScopesToBranch;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('inventory.view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('inventory.view') && $this->actorMayAccessBranch($user, $inventory->branch));
    }
}
