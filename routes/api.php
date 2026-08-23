<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SupplierController;
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

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('user', [AuthController::class, 'user'])->name('user');
        Route::get('config', ConfigController::class)->name('config');

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
    });
});
