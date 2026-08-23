<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return $this->user()->can('update', $brand);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('slug')) {
                return;
            }

            /** @var Brand $brand */
            $brand = $this->route('brand');

            $slugTaken = Brand::query()
                ->where('business_id', $brand->business_id)
                ->where('slug', $this->input('slug'))
                ->where('id', '!=', $brand->id)
                ->exists();

            if ($slugTaken) {
                $validator->errors()->add('name', 'A brand with this name already exists for this business.');
            }
        });
    }
}
