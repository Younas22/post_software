<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = [
        'sale_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax',
        'refund_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'refund_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
