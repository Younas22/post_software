<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
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

        return ApiResponse::success($business->load('businessType'), 'Business retrieved successfully.');
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->authorizedBusiness($request, 'businesses.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone'],
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

        return ApiResponse::success($business, 'Business updated successfully.');
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
