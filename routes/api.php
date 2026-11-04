<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Token auth, health, config bootstrap, and employee (staff) management for
| the future React/Electron/PWA clients. No POS resource endpoints
| (products, sales, inventory, ...) belong here yet; those arrive with the
| Core POS phase. Every route here already sits behind Laravel's default
| 'api' middleware group (registered via bootstrap/app.php's
| withRouting(api: ...)), which includes throttle:api.
|
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', fn () => ApiResponse::success(['status' => 'ok'], 'Service is healthy.'))->name('health');

    // Public branding for a business type's own login URL (/login/{slug}).
    Route::get('public/branding/{slug}', [BrandingController::class, 'show'])
        ->middleware('throttle:60,1')
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name('public.branding');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    // Password reset — JSON wrapper around Laravel's own Password broker
    // (see AuthController), previously only reachable via session/Blade
    // forms (docs/LARAVEL_API_DISCOVERY.md §21).
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('user', [AuthController::class, 'user'])->name('user');
        Route::get('config', ConfigController::class)->name('config');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription');
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');

        // Self-service account actions for the currently authenticated user.
        Route::put('user/password', [AccountController::class, 'updatePassword'])->name('user.password.update');
        Route::put('user/profile', [AccountController::class, 'updateProfile'])->name('user.profile.update');

        // Notifications — reads back what the scheduled subscription/device
        // checks already write via the Notifiable trait on User.
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        // "My business" profile — previously Admin-Blade-only.
        Route::get('business', [BusinessController::class, 'show'])->name('business.show');
        Route::get('business/locale-options', [BusinessController::class, 'localeOptions'])->name('business.locale-options');
        Route::put('business', [BusinessController::class, 'update'])->name('business.update');
        Route::put('business/settings', [BusinessController::class, 'updateSettings'])->name('business.settings.update');

        // Branches — previously Admin-Blade-only; enforces max_branches via
        // the same PackageLimitService the Admin panel already uses.
        Route::middleware('permission:businesses.view')->get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::middleware('permission:businesses.update')->group(function () {
            Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
            Route::match(['put', 'patch'], 'branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::patch('branches/{branch}/activate', [BranchController::class, 'activate'])->name('branches.activate');
            Route::patch('branches/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('branches.deactivate');
        });
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');

        // Employees (staff) — a Client Owner/Business Admin manages their
        // own team; a Super Admin may act on behalf of any client (see
        // StoreEmployeeRequest/EmployeeController). 'create' is registered
        // before '{employee}' for the same reason routes/admin.php does —
        // an unconstrained {employee} would otherwise swallow "create" as
        // an id and 404 on model binding.
        Route::middleware('permission:users.create')->post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::middleware('permission:users.view')->group(function () {
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });
        Route::middleware('permission:users.update')->group(function () {
            Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::patch('employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
            Route::patch('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        });

        // Product catalog (categories, brands, products) — one shared
        // products.* permission set governs all three, mirroring how
        // packages.* already governs both Package and Feature management.
        Route::middleware('permission:products.view')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
            Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
            Route::get('brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        });
        Route::middleware('permission:products.create')->group(function () {
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
        });
        Route::middleware('permission:products.update')->group(function () {
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::match(['put', 'patch'], 'brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->name('products.update');
        });
        Route::middleware('permission:products.delete')->group(function () {
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        });

        // Inventory — 'low-stock' and '{product}/movements' are registered
        // before the bare '{product}'-style dynamic segments they'd
        // otherwise collide with are even reachable (there is no bare
        // '/inventory/{id}' route here at all, by design: the current
        // balance is read via the index filters, not a single-resource
        // show, since a balance is keyed by branch+product, not one id).
        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
            Route::get('stock-movements', [InventoryController::class, 'globalMovements'])->name('inventory.stock-movements');
            Route::get('products/{product}/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        });
        Route::middleware('permission:inventory.adjust')->post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        // Customers / Suppliers
        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        });
        Route::middleware('permission:customers.create')->post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::middleware('permission:customers.update')->match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::middleware('permission:customers.delete')->delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::middleware('permission:suppliers.view')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        });
        Route::middleware('permission:suppliers.create')->post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::middleware('permission:suppliers.update')->match(['put', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::middleware('permission:suppliers.delete')->delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Purchases — no destroy route exists; a purchase is voided via
        // 'cancel', never deleted (see the purchases migration's comment).
        Route::middleware('permission:purchases.view')->group(function () {
            Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        });
        Route::middleware('permission:purchases.create')->post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::middleware('permission:purchases.update')->group(function () {
            Route::match(['put', 'patch'], 'purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
            Route::patch('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        });
        Route::middleware('permission:purchases.cancel')->patch('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

        // Expenses — branch-scoped like Purchase; previously absent
        // entirely (no model/table/route — see
        // docs/LARAVEL_API_DISCOVERY.md §21).
        Route::middleware('permission:expenses.view')->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        });
        Route::middleware('permission:expenses.create')->post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::middleware('permission:expenses.update')->match(['put', 'patch'], 'expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::middleware('permission:expenses.delete')->delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        // Installment plans — only usable by business types with the
        // `installments` module enabled (enforced on create). 'summary' is
        // registered before '{installmentPlan}' so it isn't read as an id.
        Route::middleware('permission:installments.view')->group(function () {
            Route::get('installments', [InstallmentPlanController::class, 'index'])->name('installments.index');
            Route::get('installments/summary', [InstallmentPlanController::class, 'summary'])->name('installments.summary');
            Route::get('installments/{installmentPlan}', [InstallmentPlanController::class, 'show'])->whereNumber('installmentPlan')->name('installments.show');
        });
        Route::middleware('permission:installments.create')->post('installments', [InstallmentPlanController::class, 'store'])->name('installments.store');
        Route::middleware('permission:installments.collect')->post('installments/{installmentPlan}/payments', [InstallmentPlanController::class, 'collect'])->whereNumber('installmentPlan')->name('installments.collect');
        Route::middleware('permission:installments.cancel')->patch('installments/{installmentPlan}/cancel', [InstallmentPlanController::class, 'cancel'])->whereNumber('installmentPlan')->name('installments.cancel');

        // Sales (POS) — see SaleService for the full "CRITICAL FLOW" this
        // sits behind (permission -> subscription -> feature -> stock ->
        // authoritative pricing -> idempotency -> atomic persistence).
        Route::middleware('permission:sales.view')->group(function () {
            Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
            Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
            Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        });
        Route::middleware('permission:sales.create')->post('sales', [SaleController::class, 'store'])->name('sales.store');

        // Returns — always created against a specific sale
        // (POST sales/{sale}/returns), never as a bare top-level resource.
        Route::middleware('permission:returns.view')->group(function () {
            Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
            Route::get('returns/{return}', [ReturnController::class, 'show'])->name('returns.show');
        });
        Route::middleware('permission:returns.process')->post('sales/{sale}/returns', [ReturnController::class, 'store'])->name('returns.store');

        // Cash sessions — 'current'/'open' are static and registered before
        // the '{cashSession}' wildcard group for the same reason every
        // other 'create'-vs-'{id}' pair in this file is ordered that way.
        Route::middleware('permission:cash_sessions.view')->get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
        // No permission:* middleware below: CashSessionPolicy's own view()/
        // open()/operate() abilities already check the right permission per
        // action (operate OR manage OR simply owning the session) — a single
        // route-level permission gate would wrongly block one of those valid
        // paths, so authorization is left entirely to the controller's
        // $this->authorize() calls here.
        Route::get('cash-sessions/current', [CashSessionController::class, 'current'])->name('cash-sessions.current');
        Route::post('cash-sessions/open', [CashSessionController::class, 'open'])->name('cash-sessions.open');
        Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show'])->name('cash-sessions.show');
        Route::post('cash-sessions/{cashSession}/cash-in', [CashSessionController::class, 'cashIn'])->name('cash-sessions.cash-in');
        Route::post('cash-sessions/{cashSession}/cash-out', [CashSessionController::class, 'cashOut'])->name('cash-sessions.cash-out');
        Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');

        // Devices — register is called by an authenticated user (the admin
        // setting up a new terminal); current is called by the DEVICE's own
        // credential afterward (see EnsureDeviceIsActive / Device::class).
        // Both sit behind auth:sanctum since Sanctum resolves either
        // tokenable transparently — 'device.active' is what actually
        // enforces that /current specifically requires a device token.
        Route::middleware('permission:devices.manage')->post('devices/register', [DeviceController::class, 'register'])->name('devices.register');
        Route::middleware('device.active')->get('devices/current', [DeviceController::class, 'current'])->name('devices.current');

        // Sync (Electron initial + incremental) — device-authenticated only,
        // never gated by a user permission: there is no user in this flow.
        // Every method resolves its business/branch from the device's own
        // record (see SyncController), so there is nothing here for a
        // request parameter to spoof.
        Route::middleware('device.active')->prefix('sync')->name('sync.')->group(function () {
            Route::get('bootstrap', [SyncController::class, 'bootstrap'])->name('bootstrap');
            Route::get('categories', [SyncController::class, 'categories'])->name('categories');
            Route::get('brands', [SyncController::class, 'brands'])->name('brands');
            Route::get('products', [SyncController::class, 'products'])->name('products');
            Route::get('customers', [SyncController::class, 'customers'])->name('customers');
            Route::get('inventory', [SyncController::class, 'inventory'])->name('inventory');
            Route::post('sales', [SyncController::class, 'sales'])->name('sales');
        });

        // Reports — every method resolves and re-verifies its own tenant
        // scope internally (see ReportController::authorizedBusiness());
        // there is deliberately no single blanket permission:* middleware
        // here since access also depends on subscription + feature state,
        // which a route middleware can't express.
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('purchases', [ReportController::class, 'purchases'])->name('purchases');
            Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
            Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
            Route::get('products', [ReportController::class, 'products'])->name('products');
            Route::get('customers', [ReportController::class, 'customers'])->name('customers');
            Route::get('profit', [ReportController::class, 'profit'])->name('profit');
        });
    });
});
