<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place a Purchase's totals are computed and its stock effects are
 * applied. Line-level numbers (price, discount, tax) are taken from the
 * request, but every summary total is derived here from those lines — a
 * frontend-supplied subtotal/tax/total is never trusted or stored verbatim.
 */
class PurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int, purchase_price: float, discount?: float, tax?: float}>  $items
     */
    public function create(
        Branch $branch,
        Supplier $supplier,
        User $user,
        array $items,
        ?string $invoiceReference,
        string $purchaseDate,
        ?string $notes,
        string $status = 'received',
    ): Purchase {
        return DB::transaction(function () use ($branch, $supplier, $user, $items, $invoiceReference, $purchaseDate, $notes, $status) {
            $lines = $this->computeLines($items);

            $purchase = new Purchase();
            $purchase->business_id = $branch->business_id;
            $purchase->branch_id = $branch->id;
            $purchase->supplier_id = $supplier->id;
            $purchase->user_id = $user->id;
            $purchase->invoice_reference = $invoiceReference;
            $purchase->purchase_date = $purchaseDate;
            $purchase->subtotal = $lines['subtotal'];
            $purchase->discount = $lines['discount'];
            $purchase->tax = $lines['tax'];
            $purchase->total = $lines['total'];
            $purchase->status = $status;
            $purchase->notes = $notes;
            $purchase->save();

            foreach ($lines['items'] as $line) {
                $purchase->items()->create($line);
            }

            if ($status === 'received') {
                $this->applyStock($purchase);
            }

            return $purchase->fresh(['items.product', 'supplier', 'branch']);
        });
    }

    public function receive(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! $purchase->isPending()) {
                throw new \App\Exceptions\InvalidStateTransitionException('Only a pending purchase can be received.');
            }

            $purchase->status = 'received';
            $purchase->save();

            $this->applyStock($purchase, $user);

            return $purchase->fresh(['items.product', 'supplier', 'branch']);
        });
    }

    public function cancel(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === 'cancelled') {
                throw new \App\Exceptions\InvalidStateTransitionException('This purchase is already cancelled.');
            }

            if ($purchase->isReceived()) {
                $branch = $purchase->branch;

                foreach ($purchase->items as $item) {
                    // allowNegative: reversing a booking error must succeed
                    // even if the stock it added has since been partly sold.
                    $this->stock->adjust(
                        branch: $branch,
                        product: $item->product,
                        quantity: -$item->quantity,
                        type: 'adjustment',
                        reference: $purchase,
                        user: $user,
                        notes: "Reversed: purchase #{$purchase->id} cancelled",
                        allowNegative: true,
                    );
                }
            }

            $purchase->status = 'cancelled';
            $purchase->save();

            return $purchase->fresh(['items.product', 'supplier', 'branch']);
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, purchase_price: float, discount?: float, tax?: float}>  $items
     * @return array{subtotal: float, discount: float, tax: float, total: float, items: array<int, array<string, mixed>>}
     */
    private function computeLines(array $items): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $price = (float) $item['purchase_price'];
            $discount = (float) ($item['discount'] ?? 0);
            $tax = (float) ($item['tax'] ?? 0);

            $lineGross = $quantity * $price;
            $lineTotal = $lineGross - $discount + $tax;

            $subtotal += $lineGross;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $lines[] = [
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'purchase_price' => $price,
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discountTotal, 2),
            'tax' => round($taxTotal, 2),
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
            'items' => $lines,
        ];
    }

    private function applyStock(Purchase $purchase, ?User $user = null): void
    {
        $branch = $purchase->branch;

        foreach ($purchase->items as $item) {
            $this->stock->adjust(
                branch: $branch,
                product: $item->product,
                quantity: $item->quantity,
                type: 'purchase',
                reference: $purchase,
                user: $user ?? $purchase->user,
            );
        }
    }
}
