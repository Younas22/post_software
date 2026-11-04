<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\LocaleOptionsService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * "My business" profile — token-authed equivalent of a slice of
 * Admin\BusinessController (show/update/updateSettings), scoped to the
 * actor's own business only. Previously Admin-Blade-only (see
 * docs/LARAVEL_API_DISCOVERY.md §13/§21).
 */
class BusinessController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request, 'businesses.view');

        return ApiResponse::success($business->load('businessType')->append('logo_url'), 'Business retrieved successfully.');
    }

    /**
     * The currencies/timezones this business may pick from — the Super
     * Admin's enabled set (System Settings) plus the business's own current
     * values. Readable by anyone who can view the business profile.
     */
    public function localeOptions(Request $request, LocaleOptionsService $locale): JsonResponse
    {
        $business = $this->authorizedBusiness($request, 'businesses.view');

        return ApiResponse::success([
            'currencies' => $locale->currencyOptions($business->currency),
            'timezones' => $locale->timezoneOptions($business->timezone),
            'default_currency' => $locale->defaultCurrency(),
            'default_timezone' => $locale->defaultTimezone(),
        ], 'Locale options retrieved successfully.');
    }

    public function update(Request $request, LocaleOptionsService $locale): JsonResponse
    {
        $business = $this->authorizedBusiness($request, 'businesses.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'currency' => ['required', 'string', Rule::in($locale->allowedCurrencyCodes($business->currency))],
            'timezone' => ['required', 'string', Rule::in($locale->allowedTimezoneIds($business->timezone))],
        ]);

        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $data['logo'] = $request->file('logo')->store('business-logos', 'public');
        } else {
            unset($data['logo']);
        }

        $business->update($data);

        return ApiResponse::success($business->append('logo_url'), 'Business updated successfully.');
    }

    public function updateSettings(Request $request, SettingsService $settings): JsonResponse
    {
        $business = $this->authorizedBusiness($request, 'settings.update');

        $keys = array_keys($settings->keysForScope('business'));
        $data = $request->validate(
            collect($keys)->mapWithKeys(fn ($key) => [$key => ['sometimes', 'nullable']])->all()
        );

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $settings->set($key, $data[$key], business: $business);
            }
        }

        $resolved = collect($keys)->mapWithKeys(fn ($key) => [$key => $settings->resolve($key, business: $business)]);

        return ApiResponse::success($resolved, 'Business settings updated successfully.');
    }

    private function authorizedBusiness(Request $request, string $permission): Business
    {
        $user = $request->user();

        if (! $user->is_super_admin && ! $user->hasPermission($permission)) {
            abort(403, 'This action is unauthorized.');
        }

        $businessId = $user->defaultBusinessId() ?? $request->integer('business_id');
        $business = $businessId ? Business::query()->find($businessId) : null;

        if (! $business || (! $user->is_super_admin && $business->client_id !== $user->client_id)) {
            abort(404, 'The requested resource was not found.');
        }

        return $business;
    }
}
