<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'business_id',
        'device_id',
        'client_transaction_id',
        'request_hash',
        'response_status',
        'response_body',
        'subject_type',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
