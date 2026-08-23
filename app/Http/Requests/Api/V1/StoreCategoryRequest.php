<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessScope;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    use ValidatesBusinessScope;

    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('products.create');
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
            'business_id' => $this->businessIdRules(),
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $business = $this->validateResolvedBusiness($validator);

            if (! $business) {
                return;
            }

            $slugTaken = Category::query()
                ->where('business_id', $business->id)
                ->where('slug', $this->input('slug'))
                ->exists();

            if ($slugTaken) {
                $validator->errors()->add('name', 'A category with this name already exists for this business.');
            }
        });
    }
}
