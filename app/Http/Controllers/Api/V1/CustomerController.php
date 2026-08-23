<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Models\Business;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
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

        return ApiResponse::collection($customers, 'Customers retrieved successfully.');
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->business_id ?? $request->integer('business_id'));
        $this->authorize('create', [Customer::class, $business]);

        $customer = new Customer();
        $customer->business_id = $business->id;
        $customer->fill($request->safe()->only(['name', 'phone', 'email', 'address', 'notes', 'status']));
        $customer->save();

        return ApiResponse::success($customer, 'Customer created successfully.', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return ApiResponse::success($customer, 'Customer retrieved successfully.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->fill($request->safe()->only(['name', 'phone', 'email', 'address', 'notes', 'status']));
        $customer->save();

        return ApiResponse::success($customer, 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return ApiResponse::success(null, 'Customer deleted successfully.');
    }
}
