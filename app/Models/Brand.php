<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business-scoped product brand. See Category for the same
 * per-business-not-per-client rationale.
 */
class Brand extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * business_id is server-resolved only — see Category::$fillable.
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
