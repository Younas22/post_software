<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\ReceiptService;
use App\Services\SaleService;
use App\Services\SubscriptionEnforcementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $actor = $request->user();

        $sales = Sale::query()
            ->with(['customer:id,name', 'user:id,name', 'branch:id,name'])
            ->whereHas('branch', function ($query) use ($actor) {
                $query->whereHas('business', fn ($q) => $q->where('client_id', $actor->client_id));
                if ($actor->business_id !== null) {
                    $query->where('business_id', $actor->business_id);
                }
                if ($actor->branch_id !== null) {
                    $query->where('id', $actor->branch_id);
                }
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('user_id', $request->integer('cashier_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sale_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sale_date', '<=', $request->string('date_to')))
            ->when($request->filled('payment_method_id'), function ($q) use ($request) {
                $q->whereHas('payments', fn ($p) => $p->where('payment_method_id', $request->integer('payment_method_id')));
            })
            ->latest('sale_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($sales, 'Sales retrieved successfully.');
    }

    public function store(StoreSaleRequest $request, SaleService $sales, SubscriptionEnforcementService $enforcement): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('create', [Sale::class, $branch]);

        $business = $branch->business;
        $client = $business->client;

        if (! $enforcement->hasPlatformAccess($client)) {
            return ApiResponse::error('This client\'s subscription does not allow platform access.', null, 403);
        }

        if (! $enforcement->isFeatureAvailable($business, 'pos')) {
            return ApiResponse::error('The POS feature is not enabled for this business.', null, 403);
        }

        $customer = $request->filled('customer_id') ? Customer::query()->find($request->integer('customer_id')) : null;

        $receipt = $sales->create(
            branch: $branch,
            cashier: $request->user(),
            customer: $customer,
            items: $request->array('items'),
            payments: $request->array('payments'),
            clientTransactionId: (string) $request->input('client_transaction_id'),
            requestPayloadForHash: $request->only(['branch_id', 'customer_id', 'items', 'payments']),
        );

        return ApiResponse::success($receipt, 'Sale completed successfully.', 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return ApiResponse::success(
            $sale->load(['items.product', 'payments.paymentMethod', 'customer', 'user:id,name', 'branch']),
            'Sale retrieved successfully.',
        );
    }

    /**
     * Clean, structured receipt data for a completed sale — never trusts
     * the sale id alone; the same tenant/branch check as show() applies
     * (see SalePolicy::view / ScopesToBranch). The Web app prints this via
     * the browser; Electron drives thermal printing from it — neither is
     * implemented here, this only shapes the data both will consume.
     */
    public function receipt(Sale $sale, ReceiptService $receipts): JsonResponse
    {
        $this->authorize('view', $sale);

        return ApiResponse::success($receipts->build($sale), 'Receipt retrieved successfully.');
    }
}
