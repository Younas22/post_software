<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InstallmentPlan;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CollectInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('installments.collect');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('payment_method_id')) {
                return;
            }
            /** @var InstallmentPlan $plan */
            $plan = $this->route('installmentPlan');
            $method = PaymentMethod::query()->find($this->integer('payment_method_id'));
            if (! $method || ($method->business_id !== null && $method->business_id !== $plan->business_id)) {
                $validator->errors()->add('payment_method_id', 'The selected payment method is invalid.');
            }
        });
    }
}
