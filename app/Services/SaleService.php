<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place a Sale is created. Implements the platform's "CRITICAL
 * FLOW" for a POS transaction: by the time this runs, the caller
 * (SaleController + StoreSaleRequest) has already authenticated the user
 * and validated business/branch/permissions/subscription/feature/products —
 * this class owns everything from "validate stock" onward: authoritative
 * pricing, server-computed totals, idempotency, and the atomic
 * create-sale + create-items + create-payments + deduct-inventory sequence.
 *
 * Nothing here ever uses a client-supplied price, tax, or total — every
 * money figure is derived from Product::sale_price / tax_rate at the
 * moment of sale.
 */
class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly IdempotencyService $idempotency,
        private readonly ReceiptService $receipts,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int, discount?: float}>  $items
     * @param  array<int, array{payment_method_id: int, amount: float, reference?: string}>  $payments
     * @return array<string, mixed> Receipt data (see ReceiptService).
     */
    public function create(
        Branch $branch,
        User $cashier,
        ?Customer $customer,
        array $items,
        array $payments,
        string $clientTransactionId,
        array $requestPayloadForHash,
        ?Device $device = null,
        ?Carbon $saleDate = null,
    ): array {
        $business = $branch->business;

        $productIds = collect($items)->pluck('product_id')->unique()->all();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('business_id', $business->id)
            ->get()
            ->keyBy('id');

        $result = $this->idempotency->handle(
            business: $business,
            clientTransactionId: $clientTransactionId,
            payload: $requestPayloadForHash,
            device: $device,
            callback: function ($record) use ($branch, $cashier, $customer, $items, $payments, $products, $clientTransactionId, $saleDate) {
                // Deliberately checked HERE, inside the callback — this only
                // runs for a genuinely new attempt (a replayed duplicate
                // short-circuits before ever reaching this closure). Checking
                // it earlier would wrongly re-evaluate stock against an
                // IDENTICAL retried payload whose own prior success already
                // consumed exactly the stock it needed, turning a harmless
                // replay into a false "insufficient stock" failure. The
                // authoritative, race-safe check still happens under
                // StockService's row lock in persist() below regardless —
                // this is only the fast, friendly rejection for a real new
                // transaction that's actually short.
                foreach ($items as $item) {
                    $product = $products[$item['product_id']];
                    $available = $this->stock->currentQuantity($branch, $product);

                    if ($available < $item['quantity']) {
                        throw new InsufficientStockException($available, $item['quantity']);
                    }
                }

                $sale = $this->persist($branch, $cashier, $customer, $items, $payments, $products, $clientTransactionId, $saleDate);
                $record->subject()->associate($sale);
                $record->save();

                return [201, $this->receipts->build($sale)];
            },
        );

        return $result['body'];
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, discount?: float}>  $items
     * @param  array<int, array{payment_method_id: int, amount: float, reference?: string}>  $payments
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function persist(
        Branch $branch,
        User $cashier,
        ?Customer $customer,
        array $items,
        array $payments,
        $products,
        string $clientTransactionId,
        ?Carbon $saleDate = null,
    ): Sale {
        return DB::transaction(function () use ($branch, $cashier, $customer, $items, $payments, $products, $clientTransactionId, $saleDate) {
            $lines = $this->priceLines($items, $products);

            $sale = new Sale();
            $sale->business_id = $branch->business_id;
            $sale->branch_id = $branch->id;
            $sale->customer_id = $customer?->id;
            $sale->user_id = $cashier->id;
            $sale->invoice_number = $this->generateInvoiceNumber($branch->business_id);
            $sale->client_transaction_id = $clientTransactionId;
            $sale->subtotal = $lines['subtotal'];
            $sale->discount = $lines['discount'];
            $sale->tax = $lines['tax'];
            $sale->total = $lines['total'];
            $sale->status = 'completed';
            // An offline (Electron) sale carries the moment it actually
            // happened, validated <= now() by the request layer; a live POS
            // sale has none, so it's simply "now".
            $sale->sale_date = $saleDate ?? now();
            $sale->save();

            foreach ($lines['items'] as $line) {
                $sale->items()->create($line);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            // Authoritative, lock-protected stock deduction. If any line is
            // now short (a concurrent sale won the race since our earlier
            // friendly check), this throws and the ENTIRE transaction —
            // sale, items, payments included — rolls back atomically.
            foreach ($lines['items'] as $line) {
                $product = $products[$line['product_id']];

                $this->stock->adjust(
                    branch: $branch,
                    product: $product,
                    quantity: -$line['quantity'],
                    type: 'sale',
                    reference: $sale,
                    user: $cashier,
                );
            }

            return $sale;
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, discount?: float}>  $items
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return array{subtotal: float, discount: float, tax: float, total: float, items: array<int, array<string, mixed>>}
     */
    private function priceLines(array $items, $products): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        foreach ($items as $item) {
            $product = $products[$item['product_id']];
            $quantity = (int) $item['quantity'];
            // Authoritative price — never the client's. Same for tax: derived
            // from the product's own tax_rate, never a client-supplied figure.
            $price = (float) $product->sale_price;
            $discount = (float) ($item['discount'] ?? 0);
            $lineGross = $quantity * $price;
            $tax = $product->tax_rate !== null ? round($lineGross * (float) $product->tax_rate / 100, 2) : 0.0;
            $lineTotal = $lineGross - $discount + $tax;

            $subtotal += $lineGross;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
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

    private function generateInvoiceNumber(int $businessId): string
    {
        do {
            $candidate = sprintf('INV-%d-%s-%s', $businessId, now()->format('ymd'), Str::upper(Str::random(6)));
        } while (Sale::where('business_id', $businessId)->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
