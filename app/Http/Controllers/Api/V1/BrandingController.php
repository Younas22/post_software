<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Support\ApiResponse;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;

/**
 * Public (unauthenticated) branding for a business type's own login URL —
 * e.g. the web app's /login/restaurant asks for the "restaurant" theme so
 * the sign-in page already looks like a restaurant product. Only exposes
 * presentation data that is also safe to show to anyone (colours, login
 * copy, the type's display name) — never any tenant data.
 */
class BrandingController extends Controller
{
    /**
     * The platform company's public profile (name, logo, office, support
     * contacts, live chat link) - for the login pages' footer and the
     * in-app Support page. Set by the Super Admin in System Settings.
     */
    public function platform(Branding $branding): JsonResponse
    {
        return ApiResponse::success($branding->toPublicArray(), 'Platform information retrieved successfully.');
    }

    public function show(string $slug): JsonResponse
    {
        $type = BusinessType::query()->where('slug', $slug)->where('is_active', true)->first();

        if (! $type) {
            return ApiResponse::error('Login page not found.', null, 404);
        }

        return ApiResponse::success($type->brandingPayload(), 'Branding retrieved successfully.');
    }
}
