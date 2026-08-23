<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeSubscriptionPackageRequest;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::query()->with(['client', 'package'])->latest()->paginate(15);

        return view('admin.subscriptions.index', ['subscriptions' => $subscriptions]);
    }

    public function show(Subscription $subscription): View
    {
        $this->authorize('view', $subscription);

        $subscription->load(['client', 'package']);

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
            'packages' => Package::query()->where('status', 'active')->orderBy('price')->get(),
        ]);
    }

    public function activate(Subscription $subscription, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('manage', $subscription);

        $subscription->update(['status' => 'active', 'starts_at' => $subscription->starts_at ?? now()]);

        $logger->log('subscription.activated', $subscription, 'Subscription activated for '.($subscription->client->name ?? 'client'));

        return back()->with('status', 'Subscription activated.');
    }

    public function suspend(Subscription $subscription, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('manage', $subscription);

        $subscription->update(['status' => 'suspended']);

        $logger->log('subscription.suspended', $subscription, 'Subscription suspended for '.($subscription->client->name ?? 'client'));

        return back()->with('status', 'Subscription suspended.');
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $this->authorize('manage', $subscription);

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return back()->with('status', 'Subscription cancelled.');
    }

    public function extend(ExtendSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update(['ends_at' => $request->validated('ends_at')]);

        return back()->with('status', 'Subscription extended.');
    }

    public function changePackage(ChangeSubscriptionPackageRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update(['package_id' => $request->validated('package_id')]);

        return back()->with('status', 'Package changed.');
    }
}
