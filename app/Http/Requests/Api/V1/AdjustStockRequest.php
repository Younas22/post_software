<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('inventory.adjust');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:255'],
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

            $product = Product::query()->find($this->integer('product_id'));

            if (! $product || $product->business_id !== $branch->business_id) {
                $validator->errors()->add('product_id', 'The selected product does not belong to this branch\'s business.');
            }
        });
    }
}
