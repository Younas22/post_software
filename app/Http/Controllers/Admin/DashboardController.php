<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Client;
use App\Models\Device;
use App\Models\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Super Admin landing page: real, directly-queried counts only — no
     * fabricated trends or historical deltas (none of that data exists yet).
     */
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalClients' => Client::count(),
            'activeClients' => Client::where('status', 'active')->count(),
            // "Trial"/"Expired" are subscription-level states; a client with
            // more than one matching subscription still counts once.
            'trialClients' => Client::whereHas('subscriptions', fn ($q) => $q->where('status', 'trial'))->count(),
            'expiredClients' => Client::whereHas('subscriptions', fn ($q) => $q->where('status', 'expired'))->count(),
            'businessCount' => Business::count(),
            'subscriptionCount' => Subscription::count(),
            'deviceCount' => Device::count(),
        ]);
    }
}
