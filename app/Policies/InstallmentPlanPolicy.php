<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

/**
 * Tenant isolation for installment plans — business-scoped (like Expense),
 * plus a branch pin for actors fixed to one branch (Manager/Cashier).
 */
class InstallmentPlanPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('installments.view');
    }

    public function view(User $user, InstallmentPlan $plan): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('installments.view') && $this->mayAccess($user, $plan));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('installments.create')
                && $this->actorMayAccessBusiness($user, $branch->business)
                && ($user->branch_id === null || $user->branch_id === $branch->id));
    }

    public function collect(User $user, InstallmentPlan $plan): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('installments.collect') && $this->mayAccess($user, $plan));
    }

    public function cancel(User $user, InstallmentPlan $plan): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('installments.cancel') && $this->mayAccess($user, $plan));
    }

    private function mayAccess(User $user, InstallmentPlan $plan): bool
    {
        return $this->actorMayAccessBusiness($user, $plan->business)
            && ($user->branch_id === null || $user->branch_id === $plan->branch_id);
    }
}
