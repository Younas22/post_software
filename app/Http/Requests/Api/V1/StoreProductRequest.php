<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessScope;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    use ValidatesBusinessScope;

    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('products.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_id' => $this->businessIdRules(),
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $business = $this->validateResolvedBusiness($validator);

            if (! $business) {
                return;
            }

            $this->validateCatalogOwnership($validator, $business);
            $this->validateUniqueness($validator, $business);
        });
    }

    protected function validateCatalogOwnership(Validator $validator, Business $business): void
    {
        if ($this->filled('category_id')) {
            $owned = Category::query()->where('id', $this->integer('category_id'))->where('business_id', $business->id)->exists();
            if (! $owned) {
                $validator->errors()->add('category_id', 'The selected category does not belong to this business.');
            }
        }

        if ($this->filled('brand_id')) {
            $owned = Brand::query()->where('id', $this->integer('brand_id'))->where('business_id', $business->id)->exists();
            if (! $owned) {
                $validator->errors()->add('brand_id', 'The selected brand does not belong to this business.');
            }
        }
    }

    protected function validateUniqueness(Validator $validator, Business $business): void
    {
        $skuTaken = Product::query()
            ->where('business_id', $business->id)
            ->where('sku', $this->input('sku'))
            ->exists();

        if ($skuTaken) {
            $validator->errors()->add('sku', 'This SKU is already used by another product in this business.');
        }

        if ($this->filled('barcode')) {
            $barcodeTaken = Product::query()
                ->where('business_id', $business->id)
                ->where('barcode', $this->input('barcode'))
                ->exists();

            if ($barcodeTaken) {
                $validator->errors()->add('barcode', 'This barcode is already used by another product in this business.');
            }
        }
    }
}
