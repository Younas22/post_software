<?php

use App\Http\Controllers\Client\BusinessController;
use App\Http\Controllers\Client\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('client')->name('client.')->middleware(['auth', 'client-user', 'subscription.active'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('businesses/{business}', [BusinessController::class, 'show'])
        ->middleware('permission:businesses.view')
        ->name('businesses.show');
});
