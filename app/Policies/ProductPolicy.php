<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

class ProductPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.view') && $this->actorMayAccessBusiness($user, $product->business));
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.create') && $this->actorMayAccessBusiness($user, $business));
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.update') && $this->actorMayAccessBusiness($user, $product->business));
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.delete') && $this->actorMayAccessBusiness($user, $product->business));
    }
}
