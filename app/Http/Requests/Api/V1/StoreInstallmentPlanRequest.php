<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('installments.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.serial_number' => ['nullable', 'string', 'max:100'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly'],
            'start_date' => ['required', 'date'],
            'first_due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'customer_cnic' => ['nullable', 'string', 'max:30'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_cnic' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $branch = Branch::query()->find($this->integer('branch_id'));

            if (! $branch
                || (! $user->is_super_admin && $branch->business->client_id !== $user->client_id)
                || ($user->business_id !== null && $user->business_id !== $branch->business_id)
                || ($user->branch_id !== null && $user->branch_id !== $branch->id)) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');

                return;
            }

            $customer = Customer::query()->find($this->integer('customer_id'));
            if (! $customer || $customer->business_id !== $branch->business_id) {
                $validator->errors()->add('customer_id', 'The selected customer is invalid.');
            }

            if ($this->filled('payment_method_id')) {
                $method = PaymentMethod::query()->find($this->integer('payment_method_id'));
                if (! $method || ($method->business_id !== null && $method->business_id !== $branch->business_id)) {
                    $validator->errors()->add('payment_method_id', 'The selected payment method is invalid.');
                }
            }
        });
    }
}
