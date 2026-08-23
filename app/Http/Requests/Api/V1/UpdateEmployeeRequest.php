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
 * Deliberately has no `client_id` field at all — an employee's client
 * assignment is permanent from creation; moving a user between clients is
 * not a supported operation here, by any actor including Super Admin.
 */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $employee */
        $employee = $this->route('employee');

        return $this->user()->can('update', $employee);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $employee */
        $employee = $this->route('employee');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'role' => [
                'sometimes', 'required', 'string',
                Rule::exists('roles', 'slug')->whereNotIn('slug', ['super-admin', 'client-owner']),
            ],
            'business_id' => ['sometimes', 'nullable', 'integer'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var User $employee */
            $employee = $this->route('employee');
            $clientId = $employee->client_id;

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
                }
            }
        });
    }
}
