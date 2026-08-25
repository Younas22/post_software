<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSupplierRequest;
use App\Http\Requests\Api\V1\UpdateSupplierRequest;
use App\Models\Business;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->whereHas('business', function ($query) use ($actor) {
                $query->where('client_id', $actor->client_id);
                if ($actor->business_id !== null) {
                    $query->where('id', $actor->business_id);
                }
            })
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->integer('business_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($suppliers, 'Suppliers retrieved successfully.');
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->defaultBusinessId() ?? $request->integer('business_id'));
        $this->authorize('create', [Supplier::class, $business]);

        $supplier = new Supplier();
        $supplier->business_id = $business->id;
        $supplier->fill($request->safe()->only(['name', 'phone', 'email', 'address', 'notes', 'status']));
        $supplier->save();

        return ApiResponse::success($supplier, 'Supplier created successfully.', 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return ApiResponse::success($supplier, 'Supplier retrieved successfully.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->fill($request->safe()->only(['name', 'phone', 'email', 'address', 'notes', 'status']));
        $supplier->save();

        return ApiResponse::success($supplier, 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return ApiResponse::success(null, 'Supplier deleted successfully.');
    }
}
