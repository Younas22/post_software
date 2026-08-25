<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBrandRequest;
use App\Http\Requests\Api\V1\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\Business;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $brands = Brand::query()
            ->whereHas('business', function ($query) use ($actor) {
                $query->where('client_id', $actor->client_id);
                if ($actor->business_id !== null) {
                    $query->where('id', $actor->business_id);
                }
            })
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->integer('business_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($brands, 'Brands retrieved successfully.');
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->defaultBusinessId() ?? $request->integer('business_id'));
        $this->authorize('create', [Brand::class, $business]);

        $brand = new Brand();
        $brand->business_id = $business->id;
        $brand->fill($request->safe()->only(['name', 'slug', 'status']));
        $brand->save();

        return ApiResponse::success($brand, 'Brand created successfully.', 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        return ApiResponse::success($brand, 'Brand retrieved successfully.');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->fill($request->safe()->only(['name', 'slug', 'status']));
        $brand->save();

        return ApiResponse::success($brand, 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return ApiResponse::success(null, 'Brand deleted successfully.');
    }
}
