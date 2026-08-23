<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseRequest;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $actor = $request->user();

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'branch:id,name'])
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
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('purchase_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('purchase_date', '<=', $request->string('date_to')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($purchases, 'Purchases retrieved successfully.');
    }

    public function store(StorePurchaseRequest $request, PurchaseService $purchases): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('create', [Purchase::class, $branch]);

        $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));

        $purchase = $purchases->create(
            branch: $branch,
            supplier: $supplier,
            user: $request->user(),
            items: $request->array('items'),
            invoiceReference: $request->string('invoice_reference')->value() ?: null,
            purchaseDate: $request->string('purchase_date'),
            notes: $request->string('notes')->value() ?: null,
            status: $request->string('status')->value() ?: 'received',
        );

        return ApiResponse::success($purchase, 'Purchase created successfully.', 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        return ApiResponse::success($purchase->load(['items.product', 'supplier', 'branch', 'user:id,name']), 'Purchase retrieved successfully.');
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        if (! $purchase->isPending()) {
            throw new InvalidStateTransitionException('Only a pending purchase can be updated.');
        }

        $purchase->fill($request->safe()->only(['invoice_reference', 'purchase_date', 'notes']));
        $purchase->save();

        return ApiResponse::success($purchase, 'Purchase updated successfully.');
    }

    public function receive(Request $request, Purchase $purchase, PurchaseService $purchases): JsonResponse
    {
        $this->authorize('update', $purchase);

        $purchase = $purchases->receive($purchase, $request->user());

        return ApiResponse::success($purchase, 'Purchase received; stock updated.');
    }

    public function cancel(Request $request, Purchase $purchase, PurchaseService $purchases): JsonResponse
    {
        $this->authorize('cancel', $purchase);

        $purchase = $purchases->cancel($purchase, $request->user());

        return ApiResponse::success($purchase, 'Purchase cancelled.');
    }
}
