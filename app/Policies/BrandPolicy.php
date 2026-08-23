<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

class BrandPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('products.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.view') && $this->actorMayAccessBusiness($user, $brand->business));
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.create') && $this->actorMayAccessBusiness($user, $business));
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.update') && $this->actorMayAccessBusiness($user, $brand->business));
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.delete') && $this->actorMayAccessBusiness($user, $brand->business));
    }
}
