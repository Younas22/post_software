<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'business_id',
        'branch_id',
        'key',
        'value',
        'type',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Read a setting's raw value for an exact scope (all-null = system-wide).
     * For scope-cascading reads (branch falling back to business, etc.), use
     * SettingsService::resolve() instead.
     */
    public static function get(string $key, ?int $clientId = null, ?int $businessId = null, ?int $branchId = null, mixed $default = null): mixed
    {
        $value = static::query()
            ->where('client_id', $clientId)
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('key', $key)
            ->value('value');

        return $value ?? $default;
    }

    /**
     * Upsert a setting's value for an exact scope. Enforces the
     * single-row-per-scope uniqueness that the database index cannot
     * express for NULL scope columns.
     */
    public static function set(string $key, mixed $value, ?int $clientId = null, ?int $businessId = null, ?int $branchId = null, string $type = 'string'): self
    {
        return static::query()->updateOrCreate(
            ['client_id' => $clientId, 'business_id' => $businessId, 'branch_id' => $branchId, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
