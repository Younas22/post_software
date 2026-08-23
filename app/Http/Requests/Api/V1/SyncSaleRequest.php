<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Customer;
use App\Models\Device;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The device itself is already authenticated by the 'device.active'
 * middleware before this request is even validated — business_id,
 * branch_id, and device_id are deliberately NOT accepted fields here, even
 * though Electron could plausibly send them: they always come from the
 * authenticated device's own record (see SyncController::sales()), never
 * from the body, matching the same "never trust a client-supplied tenant
 * id" rule every other write endpoint in this API follows.
 */
class SyncSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated entirely by the 'device.active' middleware — there is no
        // separate user permission to check in a device-authenticated flow.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_transaction_id' => ['required', 'string', 'max:100'],
            'cashier_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'offline_created_at' => ['required', 'date', 'before_or_equal:now'],
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
            /** @var Device $device */
            $device = $this->user();
            $businessId = $device->business_id;
            $branchId = $device->branch_id;

            $cashier = User::query()->find($this->integer('cashier_id'));
            if (! $cashier || $cashier->business_id !== $businessId || ($cashier->branch_id !== null && $cashier->branch_id !== $branchId)) {
                $validator->errors()->add('cashier_id', 'This cashier does not belong to this device\'s business/branch.');
            } elseif (! $cashier->isActive()) {
                $validator->errors()->add('cashier_id', 'This cashier account is deactivated.');
            }

            if ($this->filled('customer_id')) {
                $customer = Customer::query()->find($this->integer('customer_id'));
                if (! $customer || $customer->business_id !== $businessId) {
                    $validator->errors()->add('customer_id', 'This customer does not belong to this device\'s business.');
                }
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $product = $productId ? Product::query()->find($productId) : null;

                if (! $product || $product->business_id !== $businessId) {
                    $validator->errors()->add("items.{$index}.product_id", 'This product does not belong to this device\'s business.');
                } elseif (! $product->isActive()) {
                    $validator->errors()->add("items.{$index}.product_id", 'This product is not active.');
                }
            }

            foreach ((array) $this->input('payments', []) as $index => $payment) {
                $methodId = $payment['payment_method_id'] ?? null;
                $method = $methodId ? PaymentMethod::query()->find($methodId) : null;

                if (! $method || ($method->business_id !== null && $method->business_id !== $businessId)) {
                    $validator->errors()->add("payments.{$index}.payment_method_id", 'This payment method is not available for this business.');
                }
            }
        });
    }
}
