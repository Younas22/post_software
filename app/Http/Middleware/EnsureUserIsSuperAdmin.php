<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the /admin area to Super Admin accounts only. Client-side roles
 * (Client Owner, Business Admin, Manager, Cashier) are rejected even if they
 * guess the URL — access here is never decided by hiding a route.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_super_admin) {
            abort(403, 'This area is restricted to platform Super Admins.');
        }

        return $next($request);
    }
}
