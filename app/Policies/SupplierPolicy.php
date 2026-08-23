<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

class SupplierPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('suppliers.view') && $this->actorMayAccessBusiness($user, $supplier->business));
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('suppliers.create') && $this->actorMayAccessBusiness($user, $business));
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('suppliers.update') && $this->actorMayAccessBusiness($user, $supplier->business));
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('suppliers.delete') && $this->actorMayAccessBusiness($user, $supplier->business));
    }
}
