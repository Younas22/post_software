<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Models\Business;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $categories = Category::query()
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

        return ApiResponse::collection($categories, 'Categories retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->business_id ?? $request->integer('business_id'));
        $this->authorize('create', [Category::class, $business]);

        $category = new Category();
        $category->business_id = $business->id;
        $category->fill($request->safe()->only(['name', 'slug', 'status']));
        $category->save();

        return ApiResponse::success($category, 'Category created successfully.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return ApiResponse::success($category, 'Category retrieved successfully.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->fill($request->safe()->only(['name', 'slug', 'status']));
        $category->save();

        return ApiResponse::success($category, 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return ApiResponse::success(null, 'Category deleted successfully.');
    }
}
