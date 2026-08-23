<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        return $this->user()->can('create', [\App\Models\SaleReturn::class, $sale]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Sale $sale */
            $sale = $this->route('sale');

            if ($sale->status !== 'completed') {
                $validator->errors()->add('sale', 'Only a completed sale can be returned against.');

                return;
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $saleItemId = $item['sale_item_id'] ?? null;
                $saleItem = $saleItemId ? SaleItem::query()->where('sale_id', $sale->id)->find($saleItemId) : null;

                if (! $saleItem) {
                    $validator->errors()->add("items.{$index}.sale_item_id", 'This item does not belong to the specified sale.');

                    continue;
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                $remaining = $saleItem->quantity - $saleItem->returnedQuantity();

                if ($quantity > $remaining) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "Only {$remaining} unit(s) of this item remain returnable.",
                    );
                }
            }
        });
    }
}
