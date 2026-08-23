<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

/**
 * Tenant isolation for Expense — business-scoped like Customer/Supplier
 * (an expense's branch, when set, is informational, not the authorization
 * boundary; a Business Admin/Manager with no fixed branch may record an
 * expense against any of their business's branches).
 */
class ExpensePolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('expenses.view') && $this->actorMayAccessBusiness($user, $expense->business));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('expenses.create') && $this->actorMayAccessBusiness($user, $branch->business));
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('expenses.update') && $this->actorMayAccessBusiness($user, $expense->business));
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('expenses.delete') && $this->actorMayAccessBusiness($user, $expense->business));
    }
}
