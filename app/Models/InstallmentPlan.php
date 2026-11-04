<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One installment (easy-payment) sale. The goods leave stock on creation;
 * the remaining balance is split into installment_schedules and paid down
 * by installment_payments. Cancelled, never deleted.
 */
class InstallmentPlan extends Model
{
    /**
     * business_id/branch_id/customer_id/user_id and every money column are
     * server-computed in InstallmentService, never mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'markup_percent',
        'down_payment',
        'installments_count',
        'frequency',
        'start_date',
        'first_due_date',
        'guarantor_name',
        'guarantor_phone',
        'guarantor_cnic',
        'guarantor_address',
        'customer_cnic',
        'notes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['balance', 'progress'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'start_date' => 'date:Y-m-d',
            'first_due_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getBalanceAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return '0.00';
        }

        return number_format(max(0, (float) $this->total_amount - (float) $this->paid_amount), 2, '.', '');
    }

    /** Percentage of the total already paid (0-100). */
    public function getProgressAttribute(): int
    {
        $total = (float) $this->total_amount;

        return $total > 0 ? (int) min(100, round(((float) $this->paid_amount / $total) * 100)) : 100;
    }

    /**
     * Active plans with at least one schedule past its due date and not fully paid.
     *
     * @param  Builder<InstallmentPlan>  $query
     */
    public function scopeOverdue(Builder $query, string $today): void
    {
        $query->where('status', 'active')
            ->whereHas('schedules', fn ($q) => $q->where('due_date', '<', $today)->whereColumn('paid_amount', '<', 'amount'));
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<InstallmentPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InstallmentPlanItem::class);
    }

    /** @return HasMany<InstallmentSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class)->orderBy('sequence');
    }

    /** @return HasMany<InstallmentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->latest('payment_date')->latest('id');
    }
}
