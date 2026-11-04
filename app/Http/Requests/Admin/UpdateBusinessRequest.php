<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
use App\Services\LocaleOptionsService;
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
        $locale = app(LocaleOptionsService::class);
        /** @var Business|null $business */
        $business = $this->route('business');

        return [
            'client_id' => ['required', 'exists:clients,id'],
            'business_type_id' => ['required', 'exists:business_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            // Enabled options plus this business's current value, so an
            // admin narrowing the list never blocks re-saving it unchanged.
            'currency' => ['required', 'string', Rule::in($locale->allowedCurrencyCodes($business?->currency))],
            'timezone' => ['required', 'string', Rule::in($locale->allowedTimezoneIds($business?->timezone))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
