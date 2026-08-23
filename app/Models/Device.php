<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * Represents a POS device (Electron desktop app, or a mobile/web client)
 * registered under a client's subscription.
 *
 * `HasApiTokens` makes Device a second, independent Sanctum "tokenable" —
 * personal_access_tokens is already polymorphic (tokenable_type/id), so a
 * device credential issued by DeviceController::register() rides the exact
 * same `auth:sanctum` guard a User token does, but resolves `$request->user()`
 * to a Device instance instead. The two are deliberately separate identities
 * that coexist rather than merge: a cashier's own user token keeps working
 * unchanged for every existing endpoint, and a device token is only ever
 * meaningful on device-scoped routes (see the `device.active` middleware).
 *
 * `device_identifier` is always server-generated at registration
 * (App\Http\Controllers\Api\V1\DeviceController::register()) — a caller
 * supplies a branch and a human label, never the identifier itself, so
 * client-claimed device identity is never trusted as proof of anything.
 */
class Device extends Model
{
    use HasApiTokens, HasFactory, SoftDeletes;

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
