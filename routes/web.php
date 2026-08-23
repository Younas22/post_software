<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        return redirect()->route($user->is_super_admin ? 'admin.dashboard' : 'client.dashboard');
    }

    return redirect()->route('login');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/client.php';
