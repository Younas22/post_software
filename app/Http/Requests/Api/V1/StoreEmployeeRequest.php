<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Every tenant-identity field here is validated against the AUTHENTICATED
 * actor's own client, never taken at face value from the request — this is
 * the enforcement point for "never let a business user assign client_id
 * arbitrarily / another tenant's branch / an unassignable role".
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            // 'super-admin' and 'client-owner' are deliberately excluded —
            // this endpoint creates staff, never platform admins or a
            // second account owner. Validated against real DB rows, not a
            // hard-coded id list.
            'role' => [
                'required', 'string',
                Rule::exists('roles', 'slug')->whereNotIn('slug', ['super-admin', 'client-owner']),
            ],
            'business_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ];

        // A Super Admin has no client of their own, so they must name one
        // explicitly; anyone else's client is derived server-side from
        // their own account and must never be requestable.
        if ($this->user()->is_super_admin) {
            $rules['client_id'] = ['required', 'integer', 'exists:clients,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clientId = $this->user()->is_super_admin
                ? $this->integer('client_id')
                : $this->user()->client_id;

            if ($this->filled('business_id')) {
                $business = Business::query()->find($this->integer('business_id'));

                if (! $business || $business->client_id !== $clientId) {
                    $validator->errors()->add('business_id', 'The selected business does not belong to this client.');
                }
            }

            if ($this->filled('branch_id')) {
                $branch = Branch::query()->with('business')->find($this->integer('branch_id'));

                if (! $branch || $branch->business->client_id !== $clientId) {
                    $validator->errors()->add('branch_id', 'The selected branch does not belong to this client.');
                } elseif ($this->filled('business_id') && $branch->business_id !== $this->integer('business_id')) {
                    $validator->errors()->add('branch_id', 'The selected branch does not belong to the selected business.');
                }
            }
        });
    }
}
