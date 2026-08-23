<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An immutable ledger entry — every stock change, whatever caused it
 * (purchase, sale, return, manual adjustment, transfer), is written here and
 * never updated or deleted afterward. `reference` optionally points at the
 * document that caused the movement (e.g. a future Purchase or Sale).
 */
class StockMovement extends Model
{
    public const TYPES = ['purchase', 'sale', 'return', 'adjustment', 'transfer_in', 'transfer_out'];

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
