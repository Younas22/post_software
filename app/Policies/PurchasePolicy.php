<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

class PurchasePolicy
{
    use ScopesToBranch;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('purchases.view');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('purchases.view') && $this->actorMayAccessBranch($user, $purchase->branch));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('purchases.create') && $this->actorMayAccessBranch($user, $branch));
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('purchases.update') && $this->actorMayAccessBranch($user, $purchase->branch));
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('purchases.cancel') && $this->actorMayAccessBranch($user, $purchase->branch));
    }
}
