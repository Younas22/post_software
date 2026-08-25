<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Real business-data landing-page stats — previously absent for the client
 * side entirely (Client\DashboardController was a static Blade view with no
 * data wired up; see docs/LARAVEL_API_DISCOVERY.md §21). Every figure here
 * is a live SQL aggregate over this business's own tenant-scoped rows, never
 * a placeholder — a business with no sales yet simply gets zeros, which the
 * frontend renders as an empty state.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin && ! $user->hasPermission('dashboard.view')) {
            abort(403, 'This action is unauthorized.');
        }

        $businessId = $user->defaultBusinessId() ?? $request->integer('business_id');
        $business = $businessId ? Business::query()->find($businessId) : null;

        if (! $business || (! $user->is_super_admin && $business->client_id !== $user->client_id)) {
            abort(403, 'The selected business is invalid.');
        }

        $branchId = $user->branch_id ?? $request->integer('branch_id') ?: null;
        $today = now($business->timezone ?: 'UTC')->toDateString();

        $salesQuery = Sale::query()->where('business_id', $business->id)->where('status', 'completed');
        $this->scopeBranch($salesQuery, $branchId);

        $todaySales = (clone $salesQuery)->whereDate('sale_date', $today)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total),0) as total')
            ->first();

        $expenseQuery = Expense::query()->where('business_id', $business->id);
        $this->scopeBranch($expenseQuery, $branchId);
        $todayExpenses = (clone $expenseQuery)->whereDate('expense_date', $today)->sum('amount');

        $todayCost = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', $today)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->sum(DB::raw('sale_items.quantity * products.purchase_price'));

        $todayProfit = (float) $todaySales->total - (float) $todayCost - (float) $todayExpenses;

        $lowStockQuery = Product::query()
            ->join('inventories', 'inventories.product_id', '=', 'products.id')
            ->where('products.business_id', $business->id)
            ->whereColumn('inventories.quantity', '<=', 'products.minimum_stock');
        if ($branchId) {
            $lowStockQuery->where('inventories.branch_id', $branchId);
        }
        $lowStockCount = $lowStockQuery->distinct('products.id')->count('products.id');

        $customersCount = Customer::query()->where('business_id', $business->id)->where('status', 'active')->count();

        $recentSales = (clone $salesQuery)
            ->with(['customer:id,name', 'user:id,name', 'branch:id,name', 'payments.paymentMethod:id,name'])
            ->latest('sale_date')
            ->limit(8)
            ->get(['id', 'invoice_number', 'customer_id', 'user_id', 'branch_id', 'total', 'status', 'sale_date']);

        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->where('sales.sale_date', '>=', now()->subDays(30))
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as units_sold, SUM(sale_items.line_total) as revenue')
            ->groupBy('sale_items.product_id')
            ->orderByDesc('units_sold')
            ->with('product:id,name,sku')
            ->limit(5)
            ->get();

        $openCashSession = $branchId
            ? CashSession::query()->where('branch_id', $branchId)->where('user_id', $user->id)->where('status', 'open')->first(['id', 'opening_cash', 'opened_at'])
            : null;

        return ApiResponse::success([
            'today' => [
                'sales_total' => round((float) $todaySales->total, 2),
                'orders_count' => (int) $todaySales->orders,
                'expenses_total' => round((float) $todayExpenses, 2),
                'estimated_profit' => round($todayProfit, 2),
            ],
            'low_stock_count' => $lowStockCount,
            'customers_count' => $customersCount,
            'open_cash_session' => $openCashSession,
            'recent_sales' => $recentSales,
            'top_products' => $topProducts,
        ], 'Dashboard data retrieved successfully.');
    }

    private function scopeBranch(Builder $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
    }
}
