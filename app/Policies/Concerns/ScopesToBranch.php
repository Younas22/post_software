<?php

namespace App\Policies\Concerns;

use App\Models\Branch;
use App\Models\User;

/**
 * Branch-level counterpart to ScopesToBusiness: the branch's business must
 * belong to the actor's client; if the actor is pinned to one business
 * and/or one branch, they're further restricted to it.
 */
trait ScopesToBranch
{
    private function actorMayAccessBranch(User $user, Branch $branch): bool
    {
        if ($branch->business->client_id !== $user->client_id) {
            return false;
        }

        if ($user->business_id !== null && $user->business_id !== $branch->business_id) {
            return false;
        }

        if ($user->branch_id !== null && $user->branch_id !== $branch->id) {
            return false;
        }

        return true;
    }
}
