<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a POS device (future Electron desktop app, or a mobile/web
 * client) registered under a client's subscription.
 *
 * Security note (device registration is not built yet — this documents the
 * architecture it must slot into): `device_identifier` is stored as an
 * opaque unique string, but nothing today issues or verifies it. When a real
 * registration endpoint is built, the identifier must be generated
 * server-side (or verified via a signed activation token/challenge) rather
 * than accepted verbatim from the client — a caller must never be able to
 * claim an arbitrary identifier and have it trusted as proof of that
 * physical device's identity.
 */
class Device extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    public const STATUSES = ['active', 'inactive', 'blocked'];

    /**
     * @var list<string>
     */
    public const PLATFORMS = ['windows', 'mac', 'linux', 'web', 'android', 'ios'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'business_id',
        'branch_id',
        'name',
        'type',
        'platform',
        'device_identifier',
        'status',
        'last_seen_at',
        'activated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

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
}
