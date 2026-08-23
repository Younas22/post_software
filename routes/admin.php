<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\BusinessTypeController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super-admin'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Clients (Super Admin manages every client on the platform)
    // NOTE: the 'create' route must be registered before the '{client}'
    // wildcard route below — Laravel matches routes in registration order,
    // and an unconstrained {client} would otherwise swallow "create" as if
    // it were an id, fail model binding, and 404.
    Route::middleware('permission:clients.create')->group(function () {
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    });
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    });
    Route::middleware('permission:clients.update')->group(function () {
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('clients/{client}/suspend', [ClientController::class, 'suspend'])->name('clients.suspend');
        Route::patch('clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');
    });

    // Businesses (see the clients note above — 'create' must precede '{business}')
    Route::middleware('permission:businesses.create')->group(function () {
        Route::get('businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('businesses', [BusinessController::class, 'store'])->name('businesses.store');
    });
    Route::middleware('permission:businesses.view')->group(function () {
        Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
    });
    Route::middleware('permission:businesses.update')->group(function () {
        Route::get('businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::put('businesses/{business}/settings', [BusinessController::class, 'updateSettings'])->name('businesses.settings.update');

        // Branches, nested under their business
        Route::get('businesses/{business}/branches/create', [BranchController::class, 'create'])->name('businesses.branches.create');
        Route::post('businesses/{business}/branches', [BranchController::class, 'store'])->name('businesses.branches.store');
        Route::get('businesses/{business}/branches/{branch}/edit', [BranchController::class, 'edit'])->name('businesses.branches.edit');
        Route::put('businesses/{business}/branches/{branch}', [BranchController::class, 'update'])->name('businesses.branches.update');
        Route::patch('businesses/{business}/branches/{branch}/activate', [BranchController::class, 'activate'])->name('businesses.branches.activate');
        Route::patch('businesses/{business}/branches/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('businesses.branches.deactivate');
    });

    // Packages (see the clients note above — 'create' must precede '{package}')
    Route::middleware('permission:packages.create')->group(function () {
        Route::get('packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('packages', [PackageController::class, 'store'])->name('packages.store');
    });
    Route::middleware('permission:packages.view')->group(function () {
        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('packages/{package}', [PackageController::class, 'show'])->name('packages.show');
    });
    Route::middleware('permission:packages.update')->group(function () {
        Route::get('packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::patch('packages/{package}/activate', [PackageController::class, 'activate'])->name('packages.activate');
        Route::patch('packages/{package}/deactivate', [PackageController::class, 'deactivate'])->name('packages.deactivate');
    });

    // Features are package-catalog-adjacent and have no dedicated permission
    // slug in the Phase 2 permission list, so they're gated by packages.*.
    Route::middleware('permission:packages.view')->group(function () {
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
    });
    Route::middleware('permission:packages.create')->group(function () {
        Route::get('features/create', [FeatureController::class, 'create'])->name('features.create');
        Route::post('features', [FeatureController::class, 'store'])->name('features.store');
    });
    Route::middleware('permission:packages.update')->group(function () {
        Route::get('features/{feature}/edit', [FeatureController::class, 'edit'])->name('features.edit');
        Route::put('features/{feature}', [FeatureController::class, 'update'])->name('features.update');
    });

    // Subscriptions
    Route::middleware('permission:subscriptions.view')->group(function () {
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    });
    Route::middleware('permission:subscriptions.manage')->group(function () {
        Route::patch('subscriptions/{subscription}/activate', [SubscriptionController::class, 'activate'])->name('subscriptions.activate');
        Route::patch('subscriptions/{subscription}/suspend', [SubscriptionController::class, 'suspend'])->name('subscriptions.suspend');
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::patch('subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');
        Route::patch('subscriptions/{subscription}/change-package', [SubscriptionController::class, 'changePackage'])->name('subscriptions.change-package');
    });

    Route::middleware('permission:devices.view')->group(function () {
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    });
    Route::middleware('permission:devices.manage')->group(function () {
        Route::patch('devices/{device}/activate', [DeviceController::class, 'activate'])->name('devices.activate');
        Route::patch('devices/{device}/deactivate', [DeviceController::class, 'deactivate'])->name('devices.deactivate');
        Route::patch('devices/{device}/block', [DeviceController::class, 'block'])->name('devices.block');
    });

    // Business Types and Settings are grouped under "Configuration" and have
    // no dedicated permission slugs, so they're gated by settings.*.
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('business-types', [BusinessTypeController::class, 'index'])->name('business-types.index');
    });
    Route::middleware('permission:settings.update')->group(function () {
        Route::get('business-types/create', [BusinessTypeController::class, 'create'])->name('business-types.create');
        Route::post('business-types', [BusinessTypeController::class, 'store'])->name('business-types.store');
        Route::get('business-types/{businessType}/edit', [BusinessTypeController::class, 'edit'])->name('business-types.edit');
        Route::put('business-types/{businessType}', [BusinessTypeController::class, 'update'])->name('business-types.update');
        Route::patch('business-types/{businessType}/activate', [BusinessTypeController::class, 'activate'])->name('business-types.activate');
        Route::patch('business-types/{businessType}/deactivate', [BusinessTypeController::class, 'deactivate'])->name('business-types.deactivate');
    });

    Route::get('settings', [SettingController::class, 'index'])
        ->middleware('permission:settings.view')
        ->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])
        ->middleware('permission:settings.update')
        ->name('settings.update');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity_logs.view')
        ->name('activity-logs.index');
});
