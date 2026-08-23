<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

class CustomerPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('customers.view') && $this->actorMayAccessBusiness($user, $customer->business));
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('customers.create') && $this->actorMayAccessBusiness($user, $business));
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('customers.update') && $this->actorMayAccessBusiness($user, $customer->business));
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('customers.delete') && $this->actorMayAccessBusiness($user, $customer->business));
    }
}
