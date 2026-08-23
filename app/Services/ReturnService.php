<?php

namespace App\Services;

use App\Exceptions\ExcessiveReturnException;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place a return is created. The original Sale is never edited or
 * deleted to represent it — a return is always a separate record pointing
 * back at it (see the returns migration's comment). Refund amounts are
 * derived from the original sale item's own recorded line_total (never
 * recomputed from current product prices, which may have since changed),
 * so a return always refunds exactly what the customer actually paid.
 */
class ReturnService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<int, array{sale_item_id: int, quantity: int}>  $items
     */
    public function create(Sale $sale, User $user, array $items, ?string $reason): SaleReturn
    {
        return DB::transaction(function () use ($sale, $user, $items, $reason) {
            // Locking the parent sale row serializes concurrent returns
            // against it, so two simultaneous requests can't both pass the
            // "remaining returnable" check for the same units.
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $refundTotal = 0.0;

            foreach ($items as $item) {
                $saleItem = SaleItem::query()->where('sale_id', $sale->id)->findOrFail($item['sale_item_id']);
                $quantity = (int) $item['quantity'];

                $alreadyReturned = $saleItem->returnedQuantity();
                $remaining = $saleItem->quantity - $alreadyReturned;

                if ($quantity > $remaining) {
                    throw new ExcessiveReturnException($saleItem->id, $remaining, $quantity);
                }

                // Proportional share of what this line's units actually
                // netted after discount/tax, per the original sale — not a
                // fresh calculation from current product prices.
                $perUnitTotal = $saleItem->quantity > 0 ? $saleItem->line_total / $saleItem->quantity : 0;
                $perUnitTax = $saleItem->quantity > 0 ? $saleItem->tax / $saleItem->quantity : 0;

                $lineRefund = round($perUnitTotal * $quantity, 2);
                $lineTax = round($perUnitTax * $quantity, 2);
                $lineGross = round($saleItem->unit_price * $quantity, 2);

                $subtotal += $lineGross;
                $taxTotal += $lineTax;
                $refundTotal += $lineRefund;

                $lines[] = [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $saleItem->unit_price,
                    'tax' => $lineTax,
                    'refund_amount' => $lineRefund,
                ];
            }

            $return = new SaleReturn();
            $return->sale_id = $sale->id;
            $return->business_id = $sale->business_id;
            $return->branch_id = $sale->branch_id;
            $return->customer_id = $sale->customer_id;
            $return->user_id = $user->id;
            $return->reference = $this->generateReference($sale->business_id);
            $return->reason = $reason;
            $return->subtotal = round($subtotal, 2);
            $return->tax = round($taxTotal, 2);
            $return->refund_amount = round($refundTotal, 2);
            $return->status = 'completed';
            $return->save();

            foreach ($lines as $line) {
                $return->items()->create($line);

                $this->stock->adjust(
                    branch: $sale->branch,
                    product: \App\Models\Product::findOrFail($line['product_id']),
                    quantity: $line['quantity'],
                    type: 'return',
                    reference: $return,
                    user: $user,
                );
            }

            return $return->fresh(['items.product']);
        });
    }

    private function generateReference(int $businessId): string
    {
        do {
            $candidate = sprintf('RET-%d-%s-%s', $businessId, now()->format('ymd'), Str::upper(Str::random(6)));
        } while (SaleReturn::where('business_id', $businessId)->where('reference', $candidate)->exists());

        return $candidate;
    }
}
