<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The full catalog of action slugs the platform writes — centralized so
     * the search/filter UI and every logging call site agree on the same
     * strings. Dot-notation groups related actions (client.*, etc.).
     *
     * @var list<string>
     */
    public const ACTIONS = [
        'login',
        'logout',
        'client.created',
        'client.updated',
        'client.suspended',
        'client.activated',
        'package.created',
        'package.updated',
        'subscription.activated',
        'subscription.suspended',
        'subscription.expired',
        'device.activated',
        'device.blocked',
        'branch.created',
        'branch.activated',
        'branch.deactivated',
        'employee.created',
        'employee.updated',
        'employee.activated',
        'employee.deactivated',
        'settings.updated',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'business_id',
        'branch_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A short human-readable label for the affected entity, e.g. "Client #5"
     * — used wherever the log is displayed without needing to load and
     * inspect the (possibly soft-deleted, possibly gone) subject itself.
     */
    public function entityLabel(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        $type = class_basename($this->subject_type);

        return "{$type} #{$this->subject_id}";
    }
}
