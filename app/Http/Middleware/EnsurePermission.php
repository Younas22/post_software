<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Database-driven permission gate: `->middleware('permission:clients.view')`.
 * Super Admins pass every check (see User::hasPermission()); everyone else is
 * evaluated against the permissions their assigned roles actually carry.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, "You do not have the required permission: {$permission}");
        }

        return $next($request);
    }
}
