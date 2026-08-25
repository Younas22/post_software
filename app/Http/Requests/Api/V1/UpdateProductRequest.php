<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()->can('update', $product);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'brand_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => ['sometimes', Rule::in(['product', 'service'])],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'minimum_stock' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Product $product */
            $product = $this->route('product');
            $businessId = $product->business_id;

            if ($this->filled('category_id')) {
                $owned = Category::query()->where('id', $this->integer('category_id'))->where('business_id', $businessId)->exists();
                if (! $owned) {
                    $validator->errors()->add('category_id', 'The selected category does not belong to this business.');
                }
            }

            if ($this->filled('brand_id')) {
                $owned = Brand::query()->where('id', $this->integer('brand_id'))->where('business_id', $businessId)->exists();
                if (! $owned) {
                    $validator->errors()->add('brand_id', 'The selected brand does not belong to this business.');
                }
            }

            if ($this->filled('sku')) {
                $taken = Product::query()
                    ->where('business_id', $businessId)
                    ->where('sku', $this->input('sku'))
                    ->where('id', '!=', $product->id)
                    ->exists();
                if ($taken) {
                    $validator->errors()->add('sku', 'This SKU is already used by another product in this business.');
                }
            }

            if ($this->filled('barcode')) {
                $taken = Product::query()
                    ->where('business_id', $businessId)
                    ->where('barcode', $this->input('barcode'))
                    ->where('id', '!=', $product->id)
                    ->exists();
                if ($taken) {
                    $validator->errors()->add('barcode', 'This barcode is already used by another product in this business.');
                }
            }
        });
    }
}
