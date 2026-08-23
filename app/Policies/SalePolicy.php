<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

class SalePolicy
{
    use ScopesToBranch;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('sales.view') && $this->actorMayAccessBranch($user, $sale->branch));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('sales.create') && $this->actorMayAccessBranch($user, $branch));
    }
}
