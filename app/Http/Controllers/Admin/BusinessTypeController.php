<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessTypeRequest;
use App\Http\Requests\Admin\UpdateBusinessTypeRequest;
use App\Models\BusinessType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BusinessType::class);

        $businessTypes = BusinessType::query()->withCount('businesses')->orderBy('name')->paginate(15);

        return view('admin.business-types.index', ['businessTypes' => $businessTypes]);
    }

    public function create(): View
    {
        $this->authorize('create', BusinessType::class);

        return view('admin.business-types.create');
    }

    public function store(StoreBusinessTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        BusinessType::query()->create($data);

        return redirect()->route('admin.business-types.index')->with('status', 'Business type created.');
    }

    public function edit(BusinessType $businessType): View
    {
        $this->authorize('update', $businessType);

        return view('admin.business-types.edit', ['businessType' => $businessType]);
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $businessType->update($data);

        return redirect()->route('admin.business-types.index')->with('status', 'Business type updated.');
    }

    public function activate(BusinessType $businessType): RedirectResponse
    {
        $this->authorize('update', $businessType);

        $businessType->update(['is_active' => true]);

        return back()->with('status', 'Business type activated.');
    }

    public function deactivate(BusinessType $businessType): RedirectResponse
    {
        $this->authorize('update', $businessType);

        $businessType->update(['is_active' => false]);

        return back()->with('status', 'Business type deactivated.');
    }
}
