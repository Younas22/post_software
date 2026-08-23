<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Inventory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SubscriptionEnforcementService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting over the platform's own transactional tables. Every
 * report goes through the same three gates: tenant/branch scope (never the
 * request's own business_id), subscription standing, and the 'reports'
 * feature flag. All totals are computed with SQL aggregation (SUM/COUNT/
 * AVG) — no report pulls a full transaction table into a PHP collection to
 * sum it there, so result size never grows with history size.
 */
class ReportController extends Controller
{
    public function __construct(private readonly SubscriptionEnforcementService $enforcement) {}

    public function sales(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = Sale::query()->where('business_id', $business->id)->where('status', 'completed');
        $this->scopeToActorBranch($query, $request);
        $this->applyDateRange($query, $request, 'sale_date');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('cashier_id')) {
            $query->where('user_id', $request->integer('cashier_id'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('payment_method_id')) {
            $methodId = $request->integer('payment_method_id');
            $query->whereHas('payments', fn ($q) => $q->where('payment_method_id', $methodId));
        }

        $summary = (clone $query)->selectRaw(
            'COUNT(*) as transaction_count, COALESCE(SUM(subtotal),0) as subtotal, '.
            'COALESCE(SUM(discount),0) as discount, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(total),0) as total'
        )->first();

        $sales = $query->with(['customer:id,name', 'user:id,name', 'branch:id,name'])
            ->latest('sale_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::success(['summary' => $summary, 'sales' => $sales], 'Sales report generated.');
    }

    public function purchases(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = Purchase::query()->where('business_id', $business->id)->where('status', '!=', 'cancelled');
        $this->scopeToActorBranch($query, $request);
        $this->applyDateRange($query, $request, 'purchase_date');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        $summary = (clone $query)->selectRaw(
            'COUNT(*) as purchase_count, COALESCE(SUM(subtotal),0) as subtotal, '.
            'COALESCE(SUM(discount),0) as discount, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(total),0) as total'
        )->first();

        $purchases = $query->with(['supplier:id,name', 'branch:id,name'])
            ->latest('purchase_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::success(['summary' => $summary, 'purchases' => $purchases], 'Purchases report generated.');
    }

    public function inventory(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = Inventory::query()
            ->where('inventories.business_id', $business->id)
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->select('inventories.*');
        $this->scopeToActorBranch($query, $request, 'inventories.branch_id');

        if ($request->filled('branch_id')) {
            $query->where('inventories.branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->integer('category_id'));
        }

        $summary = (clone $query)->selectRaw(
            'COALESCE(SUM(inventories.quantity),0) as total_units, '.
            'COALESCE(SUM(inventories.quantity * products.purchase_price),0) as stock_value, '.
            'SUM(CASE WHEN inventories.quantity <= products.minimum_stock THEN 1 ELSE 0 END) as low_stock_count'
        )->first();

        $rows = $query->with(['product:id,name,sku,minimum_stock,category_id', 'branch:id,name'])
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::success(['summary' => $summary, 'inventory' => $rows], 'Inventory report generated.');
    }

    /**
     * Top-selling products by quantity/revenue over the given period.
     */
    public function products(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed');
        $this->scopeToActorBranch($query, $request, 'sales.branch_id');
        $this->applyDateRange($query, $request, 'sales.sale_date');

        if ($request->filled('branch_id')) {
            $query->where('sales.branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('category_id')) {
            $query->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('products.category_id', $request->integer('category_id'));
        }

        $rows = $query->selectRaw(
            'sale_items.product_id, COALESCE(SUM(sale_items.quantity),0) as quantity_sold, '.
            'COALESCE(SUM(sale_items.line_total),0) as revenue'
        )
            ->groupBy('sale_items.product_id')
            ->orderByDesc('quantity_sold')
            ->with(['product:id,name,sku'])
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::success(['products' => $rows], 'Product performance report generated.');
    }

    public function customers(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = Sale::query()->where('business_id', $business->id)->where('status', 'completed')->whereNotNull('customer_id');
        $this->scopeToActorBranch($query, $request);
        $this->applyDateRange($query, $request, 'sale_date');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $rows = $query->selectRaw(
            'customer_id, COUNT(*) as transaction_count, COALESCE(SUM(total),0) as total_spent'
        )
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->with(['customer:id,name,phone'])
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::success(['customers' => $rows], 'Customer report generated.');
    }

    /**
     * Gross profit = revenue (completed sales) minus COGS, estimated from
     * each sold product's CURRENT purchase_price (sale_items does not
     * snapshot historical cost, so this approximates cost using today's
     * price — a documented simplification, not a precise historical margin).
     */
    public function profit(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request);

        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed');
        $this->scopeToActorBranch($query, $request, 'sales.branch_id');
        $this->applyDateRange($query, $request, 'sales.sale_date');

        if ($request->filled('branch_id')) {
            $query->where('sales.branch_id', $request->integer('branch_id'));
        }

        $summary = $query->selectRaw(
            'COALESCE(SUM(sale_items.line_total),0) as revenue, '.
            'COALESCE(SUM(sale_items.quantity * products.purchase_price),0) as estimated_cost'
        )->first();

        $revenue = (float) $summary->revenue;
        $cost = (float) $summary->estimated_cost;

        return ApiResponse::success([
            'revenue' => round($revenue, 2),
            'estimated_cost' => round($cost, 2),
            'estimated_gross_profit' => round($revenue - $cost, 2),
        ], 'Profit report generated (cost estimated from current product prices).');
    }

    public function expenses(Request $request): JsonResponse
    {
        $this->authorizedBusiness($request);

        return ApiResponse::error(
            'Expense tracking is not yet implemented on this platform — this report has no data source.',
            null,
            501,
        );
    }

    /**
     * Resolves the tenant-scoped business for a report request and enforces
     * permission + subscription + feature access. business_id, when
     * present in the request, is only ever a hint used to disambiguate
     * which of the actor's own businesses to report on — it is verified
     * against the actor's own client (or, for a non-scoped actor, rejected
     * outright if it doesn't belong to them) and can never be used to view
     * another tenant's data.
     */
    private function authorizedBusiness(Request $request): Business
    {
        $user = $request->user();

        if (! $user->is_super_admin && ! $user->hasPermission('reports.view')) {
            abort(403, 'This action is unauthorized.');
        }

        $businessId = $user->business_id ?? $request->integer('business_id');
        $business = $businessId ? Business::query()->find($businessId) : null;

        if (! $business || (! $user->is_super_admin && $business->client_id !== $user->client_id)) {
            abort(403, 'The selected business is invalid.');
        }
        if ($user->business_id !== null && $user->business_id !== $business->id) {
            abort(403, 'The selected business is invalid.');
        }

        if (! $user->is_super_admin) {
            if (! $this->enforcement->hasPlatformAccess($business->client)) {
                abort(403, "This client's subscription does not allow platform access.");
            }
            if (! $this->enforcement->isFeatureAvailable($business, 'reports')) {
                abort(403, 'The Reports feature is not enabled for this business.');
            }
        }

        return $business;
    }

    /**
     * A branch-scoped actor (business_id/branch_id set on their own user
     * record) never sees another branch's data, regardless of query params.
     */
    private function scopeToActorBranch(Builder $query, Request $request, string $branchColumn = 'branch_id'): void
    {
        $user = $request->user();

        if (! $user->is_super_admin && $user->branch_id !== null) {
            $query->where($branchColumn, $user->branch_id);
        }
    }

    private function applyDateRange(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->string('date_to'));
        }
    }
}
