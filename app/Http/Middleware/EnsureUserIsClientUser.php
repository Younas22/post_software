<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the /client area to non-Super-Admin (client-side) accounts.
 * Super Admins belong in /admin and are rejected here even if they guess
 * the URL, keeping the two areas strictly separated.
 */
class EnsureUserIsClientUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->is_super_admin) {
            abort(403, 'This area is restricted to client accounts.');
        }

        return $next($request);
    }
}
