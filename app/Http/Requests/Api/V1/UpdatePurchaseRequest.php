<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Purchase $purchase */
        $purchase = $this->route('purchase');

        return $this->user()->can('update', $purchase);
    }

    /**
     * Only the non-financial header fields are editable, and only while the
     * purchase is still pending (enforced in the controller/service, not
     * here, so a "not pending" attempt reports 409 rather than a generic
     * validation 422). Quantities/prices/totals are fixed at creation —
     * correcting those means cancelling and re-entering the purchase, which
     * preserves an honest audit trail instead of silently rewriting history.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purchase_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
