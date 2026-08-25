<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A deliberately generic catalog item — no pharmacy/electronics/variant
 * columns live here. IMEI, batch, expiry, warranty, etc. attach later via
 * HasCustomFields (Phase 7) with zero schema change; per-unit serials or
 * true variants are a future product_variants table, not this one.
 */
class Product extends Model
{
    use HasCustomFields, HasFactory, SoftDeletes;

    /**
     * business_id/category_id/brand_id are server-resolved/validated in
     * StoreProductRequest/UpdateProductRequest, not blindly mass-assigned
     * from raw request input — kept out of $fillable as defense in depth,
     * matching the User::$fillable precedent for tenant-identity columns.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'type',
        'purchase_price',
        'sale_price',
        'tax_rate',
        'unit',
        'minimum_stock',
        'status',
        'description',
        'image',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'minimum_stock' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * A service is a sellable catalog item with no physical stock — POS
     * treats it exactly like a product (search, cart, pricing, tax,
     * receipt) except SaleService/StockService never touch inventory for
     * it (see those classes). Never true unless the row was explicitly
     * created with type=service; existing rows default to 'product'.
     */
    public function isService(): bool
    {
        return $this->type === 'service';
    }
}
