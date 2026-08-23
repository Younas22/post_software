<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business-scoped product category. Two businesses under the same client
 * each keep their own category tree — categories are not shared client-wide.
 */
class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * business_id is deliberately excluded — it is always server-resolved
     * from the authenticated actor's context, never taken from request
     * input (see StoreCategoryRequest), matching the User::$fillable
     * precedent for tenant-identity columns.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
