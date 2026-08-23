<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdjustStockRequest;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $actor = $request->user();

        $inventories = Inventory::query()
            ->with(['product:id,name,sku,minimum_stock', 'branch:id,name'])
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
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest('updated_at')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($inventories, 'Inventory retrieved successfully.');
    }

    public function lowStock(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $actor = $request->user();

        $inventories = Inventory::query()
            ->with(['product:id,name,sku,minimum_stock', 'branch:id,name'])
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereColumn('inventories.quantity', '<=', 'products.minimum_stock')
            ->select('inventories.*')
            ->whereHas('branch', function ($query) use ($actor) {
                $query->whereHas('business', fn ($q) => $q->where('client_id', $actor->client_id));
                if ($actor->business_id !== null) {
                    $query->where('business_id', $actor->business_id);
                }
                if ($actor->branch_id !== null) {
                    $query->where('id', $actor->branch_id);
                }
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('inventories.branch_id', $request->integer('branch_id')))
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($inventories, 'Low-stock items retrieved successfully.');
    }

    public function adjust(AdjustStockRequest $request, StockService $stock): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('adjust', $branch);

        $product = Product::query()->findOrFail($request->integer('product_id'));

        $inventory = $stock->adjust(
            branch: $branch,
            product: $product,
            quantity: $request->integer('quantity'),
            type: 'adjustment',
            user: $request->user(),
            notes: $request->string('notes')->value() ?: null,
        );

        return ApiResponse::success($inventory->fresh(['product', 'branch']), 'Stock adjusted successfully.');
    }

    /**
     * Cross-product stock-movement feed — previously only reachable one
     * product at a time via movements() below (see
     * docs/LARAVEL_API_DISCOVERY.md §21). Same StockMovement ledger, just
     * scoped by tenant/branch instead of a single product.
     */
    public function globalMovements(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $actor = $request->user();

        $movements = StockMovement::query()
            ->with(['product:id,name,sku', 'branch:id,name', 'user:id,name'])
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
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        return ApiResponse::collection($movements, 'Stock movements retrieved successfully.');
    }

    public function movements(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $actor = $request->user();

        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->when($actor->branch_id !== null, fn ($q) => $q->where('branch_id', $actor->branch_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($movements, 'Stock movements retrieved successfully.');
    }
}
