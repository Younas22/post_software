<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Feature;
use App\Models\Package;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Package::class);

        $packages = Package::query()->withCount('features')->orderBy('price')->paginate(15);

        return view('admin.packages.index', ['packages' => $packages]);
    }

    public function create(): View
    {
        $this->authorize('create', Package::class);

        return view('admin.packages.create', [
            'features' => Feature::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePackageRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->safe()->except('features');
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        $package = Package::query()->create($data);
        $package->features()->sync($request->input('features', []));

        $logger->log('package.created', $package, "Package \"{$package->name}\" created");

        return redirect()->route('admin.packages.show', $package)->with('status', 'Package created.');
    }

    public function show(Package $package): View
    {
        $this->authorize('view', $package);

        $package->load('features');

        return view('admin.packages.show', ['package' => $package]);
    }

    public function edit(Package $package): View
    {
        $this->authorize('update', $package);

        $package->load('features');

        return view('admin.packages.edit', [
            'package' => $package,
            'features' => Feature::query()->where('status', 'active')->orderBy('name')->get(),
            'assignedFeatureIds' => $package->features->pluck('id')->all(),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->safe()->except('features');
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        $package->update($data);
        $package->features()->sync($request->input('features', []));

        $logger->log('package.updated', $package, "Package \"{$package->name}\" changed");

        return redirect()->route('admin.packages.show', $package)->with('status', 'Package updated.');
    }

    public function activate(Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->update(['status' => 'active']);

        return back()->with('status', 'Package activated.');
    }

    public function deactivate(Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->update(['status' => 'inactive']);

        return back()->with('status', 'Package deactivated.');
    }
}
