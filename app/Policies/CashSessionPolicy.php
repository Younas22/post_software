<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\ScopesToBranch;

class CashSessionPolicy
{
    use ScopesToBranch;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('cash_sessions.view');
    }

    /**
     * Viewing one session: full cash_sessions.view within scope, OR it's
     * simply the actor's own session (a cashier checking their own history
     * needs no broader view permission).
     */
    public function view(User $user, CashSession $session): bool
    {
        if ($user->is_super_admin || $session->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('cash_sessions.view') && $this->actorMayAccessBranch($user, $session->branch);
    }

    public function open(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('cash_sessions.operate') && $this->actorMayAccessBranch($user, $branch));
    }

    /**
     * Cash-in / cash-out / close: the session's own cashier, or a
     * manager/admin with cash_sessions.manage acting within their scope.
     */
    public function operate(User $user, CashSession $session): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ($user->hasPermission('cash_sessions.manage') && $this->actorMayAccessBranch($user, $session->branch)) {
            return true;
        }

        return $user->hasPermission('cash_sessions.operate') && $session->user_id === $user->id;
    }
}
