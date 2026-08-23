<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleReturn::class);

        $actor = $request->user();

        $returns = SaleReturn::query()
            ->with(['sale:id,invoice_number', 'customer:id,name', 'branch:id,name'])
            ->whereHas('branch', function ($query) use ($actor) {
                $query->whereHas('business', fn ($q) => $q->where('client_id', $actor->client_id));
                if ($actor->business_id !== null) {
                    $query->where('business_id', $actor->business_id);
                }
                if ($actor->branch_id !== null) {
                    $query->where('id', $actor->branch_id);
                }
            })
            ->when($request->filled('sale_id'), fn ($q) => $q->where('sale_id', $request->integer('sale_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($returns, 'Returns retrieved successfully.');
    }

    public function store(StoreReturnRequest $request, Sale $sale, ReturnService $returns): JsonResponse
    {
        $return = $returns->create(
            sale: $sale,
            user: $request->user(),
            items: $request->array('items'),
            reason: $request->string('reason')->value() ?: null,
        );

        return ApiResponse::success($return, 'Return processed successfully.', 201);
    }

    public function show(SaleReturn $return): JsonResponse
    {
        $this->authorize('view', $return);

        return ApiResponse::success($return->load(['items.product', 'sale:id,invoice_number', 'customer', 'user:id,name']), 'Return retrieved successfully.');
    }
}
