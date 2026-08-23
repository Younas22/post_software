<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\ScopesToBusiness;

class CategoryPolicy
{
    use ScopesToBusiness;

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermission('products.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.view') && $this->actorMayAccessBusiness($user, $category->business));
    }

    public function create(User $user, Business $business): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.create') && $this->actorMayAccessBusiness($user, $business));
    }

    public function update(User $user, Category $category): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.update') && $this->actorMayAccessBusiness($user, $category->business));
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('products.delete') && $this->actorMayAccessBusiness($user, $category->business));
    }
}
