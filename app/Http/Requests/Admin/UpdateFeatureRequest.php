<?php

namespace App\Http\Requests\Admin;

use App\Models\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Feature $feature */
        $feature = $this->route('feature');

        return $this->user()->can('update', $feature);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Feature $feature */
        $feature = $this->route('feature');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('features', 'slug')->ignore($feature->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
