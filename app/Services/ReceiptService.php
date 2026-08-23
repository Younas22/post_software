<?php

namespace App\Services;

use App\Models\Sale;

/**
 * Builds the clean, structured receipt payload for a completed sale — used
 * both as part of the response returned when a sale is created and by the
 * dedicated GET /sales/{sale}/receipt endpoint, so the two can never drift
 * apart. The Web app prints this via the browser; Electron drives thermal
 * printing from it. Neither concern is implemented here — this only shapes
 * the data.
 */
class ReceiptService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Sale $sale): array
    {
        $sale->loadMissing(['business', 'branch', 'customer', 'user', 'items.product', 'payments.paymentMethod']);

        return [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => $sale->sale_date,
            'status' => $sale->status,
            'business' => [
                'name' => $sale->business->name,
                'logo' => $sale->business->logo,
                'phone' => $sale->business->phone,
                'address' => $sale->business->address,
            ],
            'branch' => [
                'name' => $sale->branch->name,
                'address' => $sale->branch->address,
                'phone' => $sale->branch->phone,
            ],
            'cashier' => [
                'name' => $sale->user->name,
            ],
            'customer' => $sale->customer ? [
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
            ] : null,
            'items' => $sale->items->map(fn ($item) => [
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'line_total' => $item->line_total,
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'method' => $payment->paymentMethod->name,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
            ])->all(),
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'tax' => $sale->tax,
            'total' => $sale->total,
        ];
    }
}
