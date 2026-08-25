<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Client::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('clients', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],

            // The Client Owner login created alongside the client — see
            // ClientController::store(). Required: a client with no way to
            // log in isn't a usable outcome of this form.
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],

            // Optional: assign a package immediately so the owner can
            // actually reach their dashboard (Client\* routes require an
            // active subscription). Left unassigned, the owner can log in
            // but every subscription-gated area stays blocked until a
            // subscription is added from the client's own page.
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }
}
