<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Models\Business;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private const SORTABLE = ['name', 'sku', 'sale_price', 'purchase_price', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            // Branch-pinned actors (Manager/Cashier) only ever see their own
            // branch's stock summed here — never another branch's, even
            // aggregated — matching every other branch-scoped query in this
            // codebase. An unpinned actor (Owner/Admin) sees the total
            // across their whole business.
            ->withSum(['inventories as stock' => function ($query) use ($actor) {
                if ($actor->branch_id !== null) {
                    $query->where('branch_id', $actor->branch_id);
                }
            }], 'quantity')
            ->whereHas('business', function ($query) use ($actor) {
                $query->where('client_id', $actor->client_id);
                if ($actor->business_id !== null) {
                    $query->where('id', $actor->business_id);
                }
            })
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->integer('business_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('sku'), fn ($q) => $q->where('sku', $request->string('sku')))
            ->when($request->filled('barcode'), fn ($q) => $q->where('barcode', $request->string('barcode')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($products, 'Products retrieved successfully.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->business_id ?? $request->integer('business_id'));
        $this->authorize('create', [Product::class, $business]);

        $product = new Product();
        $product->business_id = $business->id;
        $product->category_id = $request->integer('category_id') ?: null;
        $product->brand_id = $request->integer('brand_id') ?: null;
        $product->fill($request->safe()->only([
            'name', 'sku', 'barcode', 'purchase_price', 'sale_price', 'tax_rate',
            'unit', 'minimum_stock', 'status', 'description',
        ]));

        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();

        return ApiResponse::success($product->fresh(['category', 'brand']), 'Product created successfully.', 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $actor = $request->user();
        $product->load(['category', 'brand'])->loadSum(['inventories as stock' => function ($query) use ($actor) {
            if ($actor->branch_id !== null) {
                $query->where('branch_id', $actor->branch_id);
            }
        }], 'quantity');

        return ApiResponse::success($product, 'Product retrieved successfully.');
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        if ($request->has('category_id')) {
            $product->category_id = $request->integer('category_id') ?: null;
        }
        if ($request->has('brand_id')) {
            $product->brand_id = $request->integer('brand_id') ?: null;
        }

        $product->fill($request->safe()->only([
            'name', 'sku', 'barcode', 'purchase_price', 'sale_price', 'tax_rate',
            'unit', 'minimum_stock', 'status', 'description',
        ]));

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();

        return ApiResponse::success($product->fresh(['category', 'brand']), 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return ApiResponse::success(null, 'Product deleted successfully.');
    }
}
