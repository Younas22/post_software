<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('purchases.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'invoice_reference' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['pending', 'received'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $branch = Branch::query()->find($this->integer('branch_id'));

            if (! $branch || $branch->business->client_id !== $user->client_id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');

                return;
            }

            if ($user->business_id !== null && $user->business_id !== $branch->business_id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');

                return;
            }

            if ($user->branch_id !== null && $user->branch_id !== $branch->id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');

                return;
            }

            $supplier = Supplier::query()->find($this->integer('supplier_id'));

            if (! $supplier || $supplier->business_id !== $branch->business_id) {
                $validator->errors()->add('supplier_id', 'The selected supplier does not belong to this branch\'s business.');
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $product = $productId ? Product::query()->find($productId) : null;

                if (! $product || $product->business_id !== $branch->business_id) {
                    $validator->errors()->add("items.{$index}.product_id", 'This product does not belong to this branch\'s business.');
                }
            }
        });
    }
}
