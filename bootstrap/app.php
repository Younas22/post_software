<?php

use App\Http\Middleware\EnsureDeviceIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsClientUser;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super-admin' => EnsureUserIsSuperAdmin::class,
            'client-user' => EnsureUserIsClientUser::class,
            'permission' => EnsurePermission::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'device.active' => EnsureDeviceIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /api/* always returns the standard JSON error envelope — never an
        // HTML error page, never a stack trace/SQL/file path/env value,
        // regardless of APP_DEBUG. Web routes (admin/client Blade) are
        // untouched: this only intercepts when the request path is under
        // /api/, so existing session-flash validation-error behavior for
        // the admin panel keeps working exactly as before.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();
