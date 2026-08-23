<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()->can('update', $business);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'business_type_id' => ['required', 'exists:business_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
