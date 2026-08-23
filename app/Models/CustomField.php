<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines a dynamic field a business (or the platform, if business_id is
 * null) wants to attach to some future entity type — e.g. "IMEI" on a
 * product, "Loyalty Number" on a customer. This table only describes the
 * field; CustomFieldValue holds the actual per-record data. Neither table
 * hard-codes any concrete field name — `entity_type`/`key`/`label` are all
 * free-form, set by whoever defines the field.
 */
class CustomField extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const TYPES = ['text', 'number', 'date', 'select', 'boolean'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'entity_type',
        'key',
        'label',
        'type',
        'options',
        'is_required',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
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
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
