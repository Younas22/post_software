<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One due date of an installment plan. `status` is derived, never stored:
 * paid / partial / overdue / due (today) / upcoming.
 */
class InstallmentSchedule extends Model
{
    /** @var list<string> */
    protected $fillable = ['sequence', 'due_date', 'amount', 'paid_amount', 'paid_at'];

    /** @var list<string> */
    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function getStatusAttribute(): string
    {
        if (round((float) $this->amount - (float) $this->paid_amount, 2) <= 0) {
            return 'paid';
        }

        $today = now()->toDateString();
        $due = $this->due_date?->toDateString();

        if ($due !== null && $due < $today) {
            return 'overdue';
        }
        if ((float) $this->paid_amount > 0) {
            return 'partial';
        }

        return $due === $today ? 'due' : 'upcoming';
    }

    /** @return BelongsTo<InstallmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}
