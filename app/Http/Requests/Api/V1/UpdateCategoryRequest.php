<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $this->user()->can('update', $category);
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

            /** @var Category $category */
            $category = $this->route('category');

            $slugTaken = Category::query()
                ->where('business_id', $category->business_id)
                ->where('slug', $this->input('slug'))
                ->where('id', '!=', $category->id)
                ->exists();

            if ($slugTaken) {
                $validator->errors()->add('name', 'A category with this name already exists for this business.');
            }
        });
    }
}
