<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Installment (easy-payment) selling:
 *
 *   price lines -> + markup % -> - down payment -> split the rest into N
 *   equal schedules (last one absorbs rounding) -> goods leave stock now.
 *
 * Every stock change goes through StockService (type "sale" / "return",
 * referencing the plan), the same as a regular POS sale.
 */
class InstallmentService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * @param  list<array{product_id:int, quantity:int, unit_price?:float|string|null, serial_number?:string|null}>  $items
     * @param  array<string, mixed>  $terms
     */
    public function create(Branch $branch, User $user, Customer $customer, array $items, array $terms): InstallmentPlan
    {
        $products = Product::query()
            ->where('business_id', $branch->business_id)
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'The selected product is invalid.']);
            }
            $quantity = (int) $item['quantity'];
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' && $item['unit_price'] !== null
                ? round((float) $item['unit_price'], 2)
                : round((float) $product->sale_price, 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;
            $lines[] = compact('product', 'quantity', 'unitPrice', 'lineTotal') + ['serial' => $item['serial_number'] ?? null];
        }

        $subtotal = round($subtotal, 2);
        $markupPercent = round((float) ($terms['markup_percent'] ?? 0), 2);
        $markupAmount = round($subtotal * $markupPercent / 100, 2);
        $total = round($subtotal + $markupAmount, 2);
        $downPayment = round((float) ($terms['down_payment'] ?? 0), 2);

        if ($downPayment > $total) {
            throw ValidationException::withMessages(['down_payment' => 'The down payment cannot be more than the total price.']);
        }

        $count = (int) $terms['installments_count'];
        $financed = round($total - $downPayment, 2);
        $installment = $count > 0 ? floor(($financed / $count) * 100) / 100 : 0;
        $frequency = (string) ($terms['frequency'] ?? 'monthly');
        $firstDue = CarbonImmutable::parse($terms['first_due_date']);

        return DB::transaction(function () use ($branch, $user, $customer, $lines, $terms, $subtotal, $markupPercent, $markupAmount, $total, $downPayment, $financed, $count, $installment, $frequency, $firstDue) {
            $plan = new InstallmentPlan();
            $plan->fill([
                'markup_percent' => $markupPercent,
                'down_payment' => $downPayment,
                'installments_count' => $count,
                'frequency' => $frequency,
                'start_date' => $terms['start_date'] ?? now()->toDateString(),
                'first_due_date' => $firstDue->toDateString(),
                'guarantor_name' => $terms['guarantor_name'] ?? null,
                'guarantor_phone' => $terms['guarantor_phone'] ?? null,
                'guarantor_cnic' => $terms['guarantor_cnic'] ?? null,
                'guarantor_address' => $terms['guarantor_address'] ?? null,
                'customer_cnic' => $terms['customer_cnic'] ?? null,
                'notes' => $terms['notes'] ?? null,
            ]);
            $plan->business_id = $branch->business_id;
            $plan->branch_id = $branch->id;
            $plan->customer_id = $customer->id;
            $plan->user_id = $user->id;
            $plan->plan_number = $this->nextPlanNumber($branch->business_id);
            $plan->subtotal = $subtotal;
            $plan->markup_amount = $markupAmount;
            $plan->total_amount = $total;
            $plan->financed_amount = $financed;
            $plan->installment_amount = $installment;
            $plan->paid_amount = $downPayment;
            $plan->status = $financed <= 0 ? 'completed' : 'active';
            $plan->save();

            foreach ($lines as $line) {
                $plan->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unitPrice'],
                    'line_total' => $line['lineTotal'],
                    'serial_number' => $line['serial'],
                ]);

                if (! $line['product']->isService()) {
                    $this->stock->adjust($branch, $line['product'], -$line['quantity'], 'sale', $plan, $user, "Installment plan {$plan->plan_number}");
                }
            }

            $allocated = 0.0;
            for ($i = 0; $i < $count; $i++) {
                $amount = $i === $count - 1 ? round($financed - $allocated, 2) : $installment;
                $allocated = round($allocated + $amount, 2);
                $plan->schedules()->create([
                    'sequence' => $i + 1,
                    'due_date' => $this->dueDate($firstDue, $frequency, $i)->toDateString(),
                    'amount' => $amount,
                    'paid_amount' => 0,
                ]);
            }

            if ($downPayment > 0) {
                $payment = new InstallmentPayment([
                    'amount' => $downPayment,
                    'payment_date' => $terms['start_date'] ?? now()->toDateString(),
                    'notes' => 'Down payment',
                ]);
                $payment->business_id = $plan->business_id;
                $payment->installment_plan_id = $plan->id;
                $payment->payment_method_id = $terms['payment_method_id'] ?? null;
                $payment->user_id = $user->id;
                $payment->receipt_number = $this->nextReceiptNumber($plan->business_id);
                $payment->is_down_payment = true;
                $payment->save();
            }

            return $plan;
        });
    }

    /**
     * Records a payment and allocates it to the oldest unpaid schedules first.
     */
    public function collect(InstallmentPlan $plan, User $user, float $amount, ?int $paymentMethodId, string $paymentDate, ?string $reference = null, ?string $notes = null): InstallmentPayment
    {
        return DB::transaction(function () use ($plan, $user, $amount, $paymentMethodId, $paymentDate, $reference, $notes) {
            /** @var InstallmentPlan $locked */
            $locked = InstallmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['amount' => 'Payments can only be collected on an active plan.']);
            }

            $amount = round($amount, 2);
            $balance = round((float) $locked->total_amount - (float) $locked->paid_amount, 2);
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'The amount is more than the remaining balance ('.number_format($balance, 2).').']);
            }

            $remaining = $amount;
            /** @var InstallmentSchedule $schedule */
            foreach ($locked->schedules()->whereColumn('paid_amount', '<', 'amount')->lockForUpdate()->get() as $schedule) {
                if ($remaining <= 0) {
                    break;
                }
                $due = round((float) $schedule->amount - (float) $schedule->paid_amount, 2);
                $apply = min($due, $remaining);
                $schedule->paid_amount = round((float) $schedule->paid_amount + $apply, 2);
                if (round((float) $schedule->amount - (float) $schedule->paid_amount, 2) <= 0) {
                    $schedule->paid_at = now();
                }
                $schedule->save();
                $remaining = round($remaining - $apply, 2);
            }

            $payment = new InstallmentPayment([
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'reference' => $reference,
                'notes' => $notes,
            ]);
            $payment->business_id = $locked->business_id;
            $payment->installment_plan_id = $locked->id;
            $payment->payment_method_id = $paymentMethodId;
            $payment->user_id = $user->id;
            $payment->receipt_number = $this->nextReceiptNumber($locked->business_id);
            $payment->is_down_payment = false;
            $payment->save();

            $locked->paid_amount = round((float) $locked->paid_amount + $amount, 2);
            if (round((float) $locked->total_amount - (float) $locked->paid_amount, 2) <= 0) {
                $locked->status = 'completed';
            }
            $locked->save();

            return $payment;
        });
    }

    /**
     * Cancels an active plan and returns its goods to stock. Money already
     * received is kept on record (refunds are a manual business decision).
     */
    public function cancel(InstallmentPlan $plan, User $user): InstallmentPlan
    {
        return DB::transaction(function () use ($plan, $user) {
            /** @var InstallmentPlan $locked */
            $locked = InstallmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['plan' => 'Only an active plan can be cancelled.']);
            }

            $branch = $locked->branch;
            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->product && ! $item->product->isService()) {
                    $this->stock->adjust($branch, $item->product, $item->quantity, 'return', $locked, $user, "Installment plan {$locked->plan_number} cancelled");
                }
            }

            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->save();

            return $locked;
        });
    }

    private function dueDate(CarbonImmutable $first, string $frequency, int $index): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $first->addWeeks($index),
            'biweekly' => $first->addWeeks($index * 2),
            default => $first->addMonthsNoOverflow($index),
        };
    }

    private function nextPlanNumber(int $businessId): string
    {
        $next = InstallmentPlan::query()->where('business_id', $businessId)->lockForUpdate()->count() + 1;
        do {
            $candidate = sprintf('INS-%05d', $next++);
        } while (InstallmentPlan::query()->where('business_id', $businessId)->where('plan_number', $candidate)->exists());

        return $candidate;
    }

    private function nextReceiptNumber(int $businessId): string
    {
        do {
            $candidate = sprintf('RCV-%s-%s', now()->format('ymd'), Str::upper(Str::random(5)));
        } while (InstallmentPayment::query()->where('business_id', $businessId)->where('receipt_number', $candidate)->exists());

        return $candidate;
    }
}
