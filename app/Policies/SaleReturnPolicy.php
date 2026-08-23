<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

class SaleReturnPolicy
{
    use ScopesToBranch;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('returns.view');
    }

    public function view(User $user, SaleReturn $return): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('returns.view') && $this->actorMayAccessBranch($user, $return->branch));
    }

    public function create(User $user, Sale $sale): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('returns.process') && $this->actorMayAccessBranch($user, $sale->branch));
    }
}
