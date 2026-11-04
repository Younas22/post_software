<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'business_type_id',
        'name',
        'slug',
        'phone',
        'email',
        'address',
        'logo',
        'favicon',
        'currency',
        'timezone',
        'status',
    ];

    /**
     * Absolute public URL of the uploaded logo (or null). Not appended by
     * default — callers opt in with ->append('logo_url') so existing
     * payloads stay unchanged.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo ? Storage::disk('public')->url($this->logo) : null);
    }

    /** Absolute public URL of the browser-tab icon (or null). Opt-in via ->append('favicon_url'). */
    protected function faviconUrl(): Attribute
    {
        return Attribute::get(fn () => $this->favicon ? Storage::disk('public')->url($this->favicon) : null);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Explicit per-business feature overrides — see FeatureAccessService for
     * how these combine with the client's package to decide final access.
     *
     * @return BelongsToMany<Feature, $this>
     */
    public function featureOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'business_feature')->withPivot('enabled')->withTimestamps();
    }

    /**
     * Business-specific dynamic field definitions (see HasCustomFields /
     * CustomField). No live UI reads this yet — it's the Phase 7 custom
     * fields foundation's missing inverse relation.
     *
     * @return HasMany<CustomField, $this>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }
}
