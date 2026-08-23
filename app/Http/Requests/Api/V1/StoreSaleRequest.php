<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Deliberately accepts no price/tax/total field anywhere in the payload —
 * those are never read from the request at all (see SaleService), not
 * merely overwritten, so there is nothing here to "trust" in the first
 * place. business_id/branch_id/user_id likewise never come from the
 * request body: branch_id is a route/body id that gets fully re-verified
 * below against the actor's own tenant, business_id is derived from it,
 * and user_id is always the authenticated actor.
 */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('sales.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'client_transaction_id' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
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

            if ($this->filled('customer_id')) {
                $customer = Customer::query()->find($this->integer('customer_id'));
                if (! $customer || $customer->business_id !== $branch->business_id) {
                    $validator->errors()->add('customer_id', 'The selected customer does not belong to this business.');
                }
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $product = $productId ? Product::query()->find($productId) : null;

                if (! $product || $product->business_id !== $branch->business_id) {
                    $validator->errors()->add("items.{$index}.product_id", 'This product does not belong to this branch\'s business.');
                } elseif (! $product->isActive()) {
                    $validator->errors()->add("items.{$index}.product_id", 'This product is not active.');
                }
            }

            foreach ((array) $this->input('payments', []) as $index => $payment) {
                $methodId = $payment['payment_method_id'] ?? null;
                $method = $methodId ? PaymentMethod::query()->find($methodId) : null;

                if (! $method || ($method->business_id !== null && $method->business_id !== $branch->business_id)) {
                    $validator->errors()->add("payments.{$index}.payment_method_id", 'This payment method is not available for this business.');
                }
            }
        });
    }
}
