<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CollectInstallmentRequest;
use App\Http\Requests\Api\V1\StoreInstallmentPlanRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Services\InstallmentService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Installment (easy-payment) plans — only for businesses whose business
 * type enables the `installments` module (see BusinessType::modules).
 */
class InstallmentPlanController extends Controller
{
    public function __construct(private readonly InstallmentService $installments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InstallmentPlan::class);

        $today = now()->toDateString();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $plans = $this->scoped($request)
            ->select('installment_plans.*')
            ->with(['customer:id,name,phone', 'branch:id,name'])
            ->withCount('items')
            ->addSelect([
                'next_due_date' => InstallmentSchedule::query()
                    ->select('due_date')
                    ->whereColumn('installment_plan_id', 'installment_plans.id')
                    ->whereColumn('paid_amount', '<', 'amount')
                    ->orderBy('sequence')
                    ->limit(1),
                'overdue_amount' => InstallmentSchedule::query()
                    ->selectRaw('COALESCE(SUM(amount - paid_amount), 0)')
                    ->whereColumn('installment_plan_id', 'installment_plans.id')
                    ->where('due_date', '<', $today)
                    ->whereColumn('paid_amount', '<', 'amount'),
            ])
            ->when($status === 'overdue', fn ($q) => $q->overdue($today))
            ->when(in_array($status, ['active', 'completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('plan_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($plans, 'Installment plans retrieved successfully.');
    }

    /**
     * Collection desk numbers: outstanding, overdue, due soon, collected.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InstallmentPlan::class);

        $today = now()->toDateString();
        $weekEnd = now()->addDays(6)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $active = $this->scoped($request)->where('status', 'active');
        $planIds = (clone $active)->pluck('id');

        $unpaid = fn () => InstallmentSchedule::query()
            ->whereIn('installment_plan_id', $planIds)
            ->whereColumn('paid_amount', '<', 'amount');

        $payments = InstallmentPayment::query()->whereIn('installment_plan_id', $this->scoped($request)->select('id'));

        $scheduleRow = fn (InstallmentSchedule $s) => [
            'id' => $s->id,
            'installment_plan_id' => $s->installment_plan_id,
            'plan_number' => $s->plan?->plan_number,
            'customer' => $s->plan?->customer ? ['id' => $s->plan->customer->id, 'name' => $s->plan->customer->name, 'phone' => $s->plan->customer->phone] : null,
            'sequence' => $s->sequence,
            'due_date' => $s->due_date?->toDateString(),
            'amount' => $s->amount,
            'paid_amount' => $s->paid_amount,
            'remaining' => number_format((float) $s->amount - (float) $s->paid_amount, 2, '.', ''),
            'status' => $s->status,
        ];

        return ApiResponse::success([
            'active_count' => (clone $active)->count(),
            'completed_count' => $this->scoped($request)->where('status', 'completed')->count(),
            'outstanding' => number_format((float) (clone $active)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total')->value('total'), 2, '.', ''),
            'overdue_amount' => number_format((float) $unpaid()->where('due_date', '<', $today)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'), 2, '.', ''),
            'overdue_plans' => $this->scoped($request)->overdue($today)->count(),
            'due_today' => number_format((float) $unpaid()->where('due_date', $today)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'), 2, '.', ''),
            'due_this_week' => number_format((float) $unpaid()->whereBetween('due_date', [$today, $weekEnd])->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'), 2, '.', ''),
            'collected_today' => number_format((float) (clone $payments)->where('payment_date', $today)->sum('amount'), 2, '.', ''),
            'collected_this_month' => number_format((float) (clone $payments)->where('payment_date', '>=', $monthStart)->sum('amount'), 2, '.', ''),
            'upcoming' => $unpaid()->whereBetween('due_date', [$today, $weekEnd])->with('plan.customer:id,name,phone')->orderBy('due_date')->limit(10)->get()->map($scheduleRow)->values(),
            'overdue' => $unpaid()->where('due_date', '<', $today)->with('plan.customer:id,name,phone')->orderBy('due_date')->limit(10)->get()->map($scheduleRow)->values(),
        ], 'Installment summary retrieved successfully.');
    }

    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('create', [InstallmentPlan::class, $branch]);
        $this->ensureModuleEnabled($branch->business);

        $customer = Customer::query()->findOrFail($request->integer('customer_id'));

        $plan = $this->installments->create(
            $branch,
            $request->user(),
            $customer,
            $request->validated('items'),
            $request->safe()->except(['items', 'branch_id', 'customer_id']),
        );

        return ApiResponse::success($this->detail($plan), 'Installment plan created successfully.', 201);
    }

    public function show(InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('view', $installmentPlan);

        return ApiResponse::success($this->detail($installmentPlan), 'Installment plan retrieved successfully.');
    }

    public function collect(CollectInstallmentRequest $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('collect', $installmentPlan);

        $payment = $this->installments->collect(
            $installmentPlan,
            $request->user(),
            (float) $request->validated('amount'),
            $request->filled('payment_method_id') ? $request->integer('payment_method_id') : null,
            (string) $request->validated('payment_date'),
            $request->validated('reference'),
            $request->validated('notes'),
        );

        return ApiResponse::success([
            'payment' => $payment->load(['paymentMethod:id,name', 'user:id,name']),
            'plan' => $this->detail($installmentPlan->fresh()),
        ], 'Payment collected successfully.', 201);
    }

    public function cancel(Request $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('cancel', $installmentPlan);

        $plan = $this->installments->cancel($installmentPlan, $request->user());

        return ApiResponse::success($this->detail($plan), 'Installment plan cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(InstallmentPlan $plan): array
    {
        $plan->load([
            'customer:id,name,phone,email,address',
            'branch:id,name',
            'user:id,name',
            'items.product:id,name,sku,type',
            'schedules',
            'payments.paymentMethod:id,name',
            'payments.user:id,name',
        ]);

        $today = now()->toDateString();
        $unpaid = $plan->schedules->filter(fn (InstallmentSchedule $s) => $s->status !== 'paid');

        return $plan->toArray() + [
            'next_due_date' => $unpaid->first()?->due_date?->toDateString(),
            'overdue_amount' => number_format($unpaid->filter(fn ($s) => $s->due_date->toDateString() < $today)->sum(fn ($s) => (float) $s->amount - (float) $s->paid_amount), 2, '.', ''),
            'paid_installments' => $plan->schedules->count() - $unpaid->count(),
        ];
    }

    /**
     * @return Builder<InstallmentPlan>
     */
    private function scoped(Request $request): Builder
    {
        $actor = $request->user();

        return InstallmentPlan::query()
            ->when(! $actor->is_super_admin, function ($q) use ($actor) {
                $q->whereHas('business', function ($query) use ($actor) {
                    $query->where('client_id', $actor->client_id);
                    if ($actor->business_id !== null) {
                        $query->where('id', $actor->business_id);
                    }
                });
            })
            ->when($actor->branch_id !== null, fn ($q) => $q->where('branch_id', $actor->branch_id))
            ->when($actor->branch_id === null && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')));
    }

    private function ensureModuleEnabled(Business $business): void
    {
        if (! $business->businessType?->hasModule('installments')) {
            abort(403, 'Installments are not enabled for this business type.');
        }
    }
}
