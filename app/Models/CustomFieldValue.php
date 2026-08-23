<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'custom_field_id',
        'value',
    ];

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * The record this value belongs to — a future Product, Customer, etc.
     * No such model exists yet, so nothing currently uses this relation;
     * it's here for those models to attach to via the HasCustomFields trait.
     *
     * @return MorphTo<Model, $this>
     */
    public function customFieldable(): MorphTo
    {
        return $this->morphTo();
    }
}
