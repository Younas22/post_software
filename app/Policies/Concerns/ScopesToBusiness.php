<?php

namespace App\Policies\Concerns;

use App\Models\Business;
use App\Models\User;

/**
 * Shared tenant-check for business-scoped resources (Category, Brand,
 * Product, Customer, Supplier, ...): the resource's business must belong to
 * the actor's client, AND if the actor is themselves pinned to one specific
 * business (Business Admin/Manager/Cashier — anyone with a non-null
 * business_id), they may only touch that one business's records. An actor
 * with a null business_id (typically a Client Owner who may run several
 * businesses under one client) is not further restricted here.
 */
trait ScopesToBusiness
{
    private function actorMayAccessBusiness(User $user, Business $business): bool
    {
        if ($business->client_id !== $user->client_id) {
            return false;
        }

        if ($user->business_id !== null && $user->business_id !== $business->id) {
            return false;
        }

        return true;
    }
}
