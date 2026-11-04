<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessTypeRequest;
use App\Http\Requests\Admin\UpdateBusinessTypeRequest;
use App\Models\BusinessType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        return view('admin.business-types.create', $this->formOptions());
    }

    public function store(StoreBusinessTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data = array_merge($data, $this->brandingData($request));

        BusinessType::query()->create($data);

        return redirect()->route('admin.business-types.index')->with('status', 'Business type created.');
    }

    public function edit(BusinessType $businessType): View
    {
        $this->authorize('update', $businessType);

        return view('admin.business-types.edit', ['businessType' => $businessType] + $this->formOptions());
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data = array_merge($data, $this->brandingData($request, $businessType));

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

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'terminologyDefaults' => config('business_types.defaults.terminology'),
            'themeDefaults' => config('business_types.defaults.theme'),
            'hideableModules' => config('business_types.hideable_modules'),
            'optionalModules' => config('business_types.optional_modules'),
            'sidebarStyles' => config('business_types.sidebar_styles'),
            'frontendUrl' => rtrim((string) config('app.frontend_url'), '/'),
        ];
    }

    /**
     * Turns the flat form input into the theme/terminology/modules JSON
     * columns. Empty terminology fields are dropped so they fall back to
     * the platform default ("Customer", "Product", ...) at read time.
     *
     * @return array<string, mixed>
     */
    private function brandingData(Request $request, ?BusinessType $existing = null): array
    {
        $theme = (array) $request->input('theme', []);
        $highlights = preg_split('/\r\n|\r|\n/', (string) ($theme['login_highlights'] ?? '')) ?: [];
        $theme['login_highlights'] = array_values(array_filter(array_map('trim', $highlights), fn ($line) => $line !== ''));
        $theme['primary_color'] = isset($theme['primary_color']) ? strtolower((string) $theme['primary_color']) : null;

        $currentImage = $existing?->theme['login_image'] ?? null;
        $theme['login_image'] = $currentImage;

        if ($request->boolean('remove_login_image') && $currentImage) {
            Storage::disk('public')->delete($currentImage);
            $theme['login_image'] = null;
        }

        if ($request->hasFile('login_image')) {
            if ($currentImage) {
                Storage::disk('public')->delete($currentImage);
            }
            $theme['login_image'] = $request->file('login_image')->store('business-types', 'public');
        }

        $terminology = collect((array) $request->input('terminology', []))
            ->only(array_keys(config('business_types.defaults.terminology')))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->all();

        $modules = (array) $request->input('modules', []);

        return [
            'icon' => $request->input('icon') ?: null,
            'theme' => array_filter($theme, fn ($value) => $value !== null && $value !== ''),
            'terminology' => $terminology,
            'modules' => [
                'hidden' => array_values((array) ($modules['hidden'] ?? [])),
                'enabled' => array_values((array) ($modules['enabled'] ?? [])),
            ],
        ];
    }
}
