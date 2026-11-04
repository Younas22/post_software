<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncSaleRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Feature;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\SaleService;
use App\Services\SettingsService;
use App\Services\SubscriptionEnforcementService;
use App\Services\SyncService;
use App\Support\ApiResponse;
use App\Support\SyncErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Every endpoint here is device-authenticated (see the 'device.active'
 * middleware) and reads the target business/branch EXCLUSIVELY from the
 * device's own record — never from a request parameter. There is no
 * business_id/branch_id input to validate or spoof in the first place,
 * which is what actually guarantees "a device can only sync its own
 * authorized business/branch."
 */
class SyncController extends Controller
{
    private const DEFAULT_PER_PAGE = 500;

    /**
     * One-shot bootstrap payload: everything a fresh Electron install needs
     * before it starts pulling the paginated, incrementally-syncable
     * resources below. Small and not paginated by design.
     */
    public function bootstrap(Request $request, SettingsService $settings, FeatureAccessService $features): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $business = $device->business;
        $branch = $device->branch;

        $businessSettings = collect($settings->keysForScope('business'))
            ->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key, business: $business)]);

        $enabledFeatures = Feature::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (Feature $feature) => $features->isEnabled($business, $feature->slug))
            ->pluck('slug')
            ->values();

        $paymentMethods = PaymentMethod::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $business->id))
            ->get(['id', 'name', 'slug'])
            ->values();

        // The RBAC catalog itself (not any one user's grants — a device has
        // no logged-in cashier yet) so Electron can pre-cache role-based UI
        // capability lookups before the first local login.
        $roles = Role::query()->with('permissions:id,slug')->get(['id', 'name', 'slug'])
            ->map(fn (Role $role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('slug')->values(),
            ]);

        return ApiResponse::success([
            'device' => ['id' => $device->id, 'name' => $device->name, 'status' => $device->status],
            'business' => ['id' => $business->id, 'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone, 'logo_url' => $business->logo_url],
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'address' => $branch->address, 'phone' => $branch->phone],
            'settings' => $businessSettings,
            'features' => $enabledFeatures,
            'payment_methods' => $paymentMethods,
            'roles' => $roles->values(),
            'permissions' => Permission::query()->get(['id', 'slug', 'name']),
            'server_time' => now()->toIso8601String(),
        ], 'Bootstrap data retrieved.');
    }

    /**
     * Uploads one offline-made sale. Idempotent on `client_transaction_id`
     * scoped to the device's own business (see IdempotencyService) — a
     * duplicate upload of the same transaction returns the ORIGINAL result
     * unchanged rather than creating a second sale or silently overwriting
     * anything. business_id/branch_id/device_id are deliberately not
     * request fields at all; both come from the authenticated device.
     */
    public function sales(SyncSaleRequest $request, SaleService $sales, SubscriptionEnforcementService $enforcement): JsonResponse
    {
        $device = $this->device($request);
        $branch = $device->branch;
        $business = $device->business;
        $client = $business->client;

        if (! $enforcement->hasPlatformAccess($client)) {
            return SyncErrorResponse::make(
                "This client's subscription does not allow platform access.",
                SyncErrorResponse::NON_RETRYABLE,
                403,
            );
        }

        if (! $enforcement->isFeatureAvailable($business, 'pos')) {
            return SyncErrorResponse::make(
                'The POS feature is not enabled for this business.',
                SyncErrorResponse::NON_RETRYABLE,
                403,
            );
        }

        $cashier = User::query()->findOrFail($request->integer('cashier_id'));
        $customer = $request->filled('customer_id') ? Customer::query()->find($request->integer('customer_id')) : null;
        $clientTransactionId = (string) $request->input('client_transaction_id');

        // Exceptions from here (InsufficientStockException, wrong-payload
        // IdempotencyConflictException) are intentionally NOT caught here —
        // they propagate to ApiExceptionRenderer, which (only for this
        // route) attaches the retryable/non_retryable/conflict
        // classification Electron needs. See SyncErrorResponse's docblock.
        $receipt = $sales->create(
            branch: $branch,
            cashier: $cashier,
            customer: $customer,
            items: $request->array('items'),
            payments: $request->array('payments'),
            clientTransactionId: $clientTransactionId,
            requestPayloadForHash: $request->only(['cashier_id', 'customer_id', 'items', 'payments']),
            device: $device,
            saleDate: Carbon::parse($request->input('offline_created_at')),
        );

        return ApiResponse::success([
            'status' => 'accepted',
            'client_transaction_id' => $clientTransactionId,
            'server_id' => Sale::query()->where('business_id', $business->id)->where('client_transaction_id', $clientTransactionId)->value('id'),
            'invoice_number' => $receipt['invoice_number'],
            'receipt' => $receipt,
        ], 'Offline sale synced successfully.', 201);
    }

    public function categories(Request $request, SyncService $sync): JsonResponse
    {
        $business = $this->device($request)->business;

        $result = $sync->paginate(
            query: Category::query()->where('business_id', $business->id),
            since: $request->query('since'),
            perPage: $this->perPage($request),
            transform: fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'status' => $c->status, 'updated_at' => $c->updated_at],
            deletedQuery: fn ($since) => Category::onlyTrashed()->where('business_id', $business->id)->where('deleted_at', '>', $since),
        );

        return ApiResponse::success($result, 'Categories sync data retrieved.');
    }

    public function brands(Request $request, SyncService $sync): JsonResponse
    {
        $business = $this->device($request)->business;

        $result = $sync->paginate(
            query: Brand::query()->where('business_id', $business->id),
            since: $request->query('since'),
            perPage: $this->perPage($request),
            transform: fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug, 'status' => $b->status, 'updated_at' => $b->updated_at],
            deletedQuery: fn ($since) => Brand::onlyTrashed()->where('business_id', $business->id)->where('deleted_at', '>', $since),
        );

        return ApiResponse::success($result, 'Brands sync data retrieved.');
    }

    public function products(Request $request, SyncService $sync): JsonResponse
    {
        $business = $this->device($request)->business;

        $result = $sync->paginate(
            query: Product::query()->where('business_id', $business->id),
            since: $request->query('since'),
            perPage: $this->perPage($request),
            transform: fn (Product $p) => [
                'id' => $p->id, 'category_id' => $p->category_id, 'brand_id' => $p->brand_id,
                'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode,
                'sale_price' => $p->sale_price, 'purchase_price' => $p->purchase_price, 'tax_rate' => $p->tax_rate,
                'unit' => $p->unit, 'minimum_stock' => $p->minimum_stock, 'status' => $p->status,
                'image' => $p->image, 'updated_at' => $p->updated_at,
            ],
            deletedQuery: fn ($since) => Product::onlyTrashed()->where('business_id', $business->id)->where('deleted_at', '>', $since),
        );

        return ApiResponse::success($result, 'Products sync data retrieved.');
    }

    public function customers(Request $request, SyncService $sync): JsonResponse
    {
        $business = $this->device($request)->business;

        $result = $sync->paginate(
            query: Customer::query()->where('business_id', $business->id),
            since: $request->query('since'),
            perPage: $this->perPage($request),
            transform: fn (Customer $c) => [
                'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email,
                'address' => $c->address, 'status' => $c->status, 'updated_at' => $c->updated_at,
            ],
            deletedQuery: fn ($since) => Customer::onlyTrashed()->where('business_id', $business->id)->where('deleted_at', '>', $since),
        );

        return ApiResponse::success($result, 'Customers sync data retrieved.');
    }

    /**
     * Branch-scoped (unlike the resources above, which are business-wide) —
     * a device only ever needs the stock levels for the branch it's
     * physically sitting in. Inventory rows are never soft-deleted (a
     * balance is just updated), so there is no deleted_ids concept here.
     */
    public function inventory(Request $request, SyncService $sync): JsonResponse
    {
        $device = $this->device($request);

        $result = $sync->paginate(
            query: Inventory::query()->where('branch_id', $device->branch_id),
            since: $request->query('since'),
            perPage: $this->perPage($request),
            transform: fn (Inventory $i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity, 'updated_at' => $i->updated_at],
        );

        return ApiResponse::success($result, 'Inventory sync data retrieved.');
    }

    private function device(Request $request): Device
    {
        /** @var Device $device */
        $device = $request->user();

        return $device;
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), 1000);
    }
}
