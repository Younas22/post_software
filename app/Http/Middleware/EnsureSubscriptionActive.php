<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionEnforcementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the /client area once a client's account is suspended/cancelled or
 * their subscription has lapsed. Super Admins never carry a client_id and
 * are always let through — they manage subscriptions, they aren't bound by
 * one.
 */
class EnsureSubscriptionActive
{
    public function __construct(private readonly SubscriptionEnforcementService $enforcement)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_super_admin) {
            return $next($request);
        }

        $client = $user->client;

        if (! $client || ! $this->enforcement->hasPlatformAccess($client)) {
            abort(402, 'Your subscription is not active. Please contact your administrator to renew access.');
        }

        return $next($request);
    }
}
