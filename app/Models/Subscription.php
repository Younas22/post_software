<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The full set of subscription lifecycle states — centralized here so
     * no controller/view/seeder hard-codes this list independently.
     *
     * @var list<string>
     */
    public const STATUSES = ['trial', 'active', 'expired', 'suspended', 'cancelled'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'package_id',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The subscription's status as it actually stands *right now*, rather
     * than whatever the stored `status` column last had written to it.
     *
     * `suspended` and `cancelled` are manual, terminal states set by an
     * admin action — they never get overridden by a date comparison.
     * `trial` and `active` are date-bound: once their respective end date
     * has passed, the subscription is effectively `expired` even if nothing
     * has (yet) run to update the stored column. This is the single place
     * that logic lives, reused by every view/controller that needs it, so a
     * scheduled job can later persist `expired` without any of them
     * needing to change.
     */
    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['suspended', 'cancelled'], true)) {
            return $this->status;
        }

        if ($this->status === 'trial') {
            return $this->trial_ends_at && $this->trial_ends_at->isPast() ? 'expired' : 'trial';
        }

        if ($this->status === 'active') {
            return $this->ends_at && $this->ends_at->isPast() ? 'expired' : 'active';
        }

        return $this->status;
    }

    public function isExpired(): bool
    {
        return $this->effectiveStatus() === 'expired';
    }

    /**
     * The Badge variant to render for this subscription's effective status —
     * kept alongside effectiveStatus() so every view renders it identically.
     */
    public function statusBadgeVariant(): string
    {
        return match ($this->effectiveStatus()) {
            'active' => 'success',
            'trial' => 'warning',
            'expired', 'cancelled' => 'danger',
            default => 'neutral',
        };
    }
}
