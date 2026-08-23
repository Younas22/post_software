<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessRequest;
use App\Http\Requests\Admin\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Business::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $businesses = Business::query()
            ->with(['client', 'businessType'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Business::class);

        return view('admin.businesses.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'businessTypes' => BusinessType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('logo');
        $data['slug'] = $this->uniqueSlug($data['name'], (int) $data['client_id']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('business-logos', 'public');
        }

        $business = Business::query()->create($data);

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Business created.');
    }

    public function show(Business $business, SettingsService $settings): View
    {
        $this->authorize('view', $business);

        $business->load(['client', 'businessType', 'branches']);

        $businessSettingKeys = $settings->keysForScope('business');
        $businessSettingValues = collect($businessSettingKeys)
            ->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key, business: $business)]);

        return view('admin.businesses.show', [
            'business' => $business,
            'businessSettingKeys' => $businessSettingKeys,
            'businessSettingValues' => $businessSettingValues,
        ]);
    }

    /**
     * Persists this business's scoped overrides for the registry's
     * business-level settings (see config/settings.php).
     */
    public function updateSettings(Request $request, Business $business, SettingsService $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $business);

        foreach (array_keys($settings->keysForScope('business')) as $key) {
            if ($request->has($key)) {
                $settings->set($key, $request->input($key), business: $business);
            }
        }

        $logger->log('settings.updated', $business, "Settings updated for \"{$business->name}\"");

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Settings updated.');
    }

    public function edit(Business $business): View
    {
        $this->authorize('update', $business);

        return view('admin.businesses.edit', [
            'business' => $business,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'businessTypes' => BusinessType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $data = $request->safe()->except('logo');

        if ($business->name !== $data['name'] || $business->client_id != $data['client_id']) {
            $data['slug'] = $this->uniqueSlug($data['name'], (int) $data['client_id'], $business->id);
        }

        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $data['logo'] = $request->file('logo')->store('business-logos', 'public');
        }

        $business->update($data);

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Business updated.');
    }

    /**
     * Slugs are unique per client (see businesses table's [client_id, slug]
     * unique index), not globally — so two different clients may each have
     * a business named "Main Store" without colliding.
     */
    private function uniqueSlug(string $name, int $clientId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Business::query()
                ->where('client_id', $clientId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }
}
