<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFeatureRequest;
use App\Http\Requests\Admin\UpdateFeatureRequest;
use App\Models\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeatureController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Feature::class);

        $features = Feature::query()->latest()->paginate(15);

        return view('admin.features.index', ['features' => $features]);
    }

    public function create(): View
    {
        $this->authorize('create', Feature::class);

        return view('admin.features.create');
    }

    public function store(StoreFeatureRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        Feature::query()->create($data);

        return redirect()->route('admin.features.index')->with('status', 'Feature created.');
    }

    public function edit(Feature $feature): View
    {
        $this->authorize('update', $feature);

        return view('admin.features.edit', ['feature' => $feature]);
    }

    public function update(UpdateFeatureRequest $request, Feature $feature): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        $feature->update($data);

        return redirect()->route('admin.features.index')->with('status', 'Feature updated.');
    }
}
