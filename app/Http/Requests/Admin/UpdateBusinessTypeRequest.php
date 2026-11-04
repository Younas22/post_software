<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BusinessTypeBrandingRules;
use App\Models\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessTypeRequest extends FormRequest
{
    use BusinessTypeBrandingRules;

    public function authorize(): bool
    {
        /** @var BusinessType $businessType */
        $businessType = $this->route('businessType');

        return $this->user()->can('update', $businessType);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BusinessType $businessType */
        $businessType = $this->route('businessType');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('business_types', 'slug')->ignore($businessType->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->brandingRules(),
        ];
    }
}
