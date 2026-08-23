<?php

namespace App\Http\Requests\Concerns;

use App\Models\Business;
use Illuminate\Validation\Validator;

/**
 * Shared business-resolution logic for any business-scoped write request
 * (Category, Brand, Product, Customer, Supplier, ...). business_id is never
 * trusted outright from the request — it is either taken from the actor's
 * own fixed business_id (Business Admin/Manager/Cashier) or, for an actor
 * with no fixed business (typically a Client Owner running several
 * businesses under one client), read from the request and then verified to
 * belong to that actor's own client before use.
 */
trait ValidatesBusinessScope
{
    /**
     * @return array<int, string>
     */
    protected function businessIdRules(): array
    {
        return $this->user()->business_id !== null
            ? ['sometimes', 'integer']
            : ['required', 'integer'];
    }

    protected function resolvedBusinessId(): ?int
    {
        $user = $this->user();

        return $user->business_id ?? ($this->integer('business_id') ?: null);
    }

    protected function validateResolvedBusiness(Validator $validator): ?Business
    {
        $user = $this->user();
        $businessId = $this->resolvedBusinessId();

        if ($businessId === null) {
            $validator->errors()->add('business_id', 'A business could not be determined for this request.');

            return null;
        }

        $business = Business::query()->find($businessId);

        if (! $business || (! $user->is_super_admin && $business->client_id !== $user->client_id)) {
            $validator->errors()->add('business_id', 'The selected business is invalid.');

            return null;
        }

        return $business;
    }
}
