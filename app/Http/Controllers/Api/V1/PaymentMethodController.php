<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only list of valid payment methods for the POS checkout screen —
 * previously only reachable via the device-authenticated sync/bootstrap
 * endpoint (see docs/LARAVEL_API_DISCOVERY.md §14/§21). Global defaults
 * (business_id null: Cash/Card/Bank/Other) plus this business's own.
 */
class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $businessId = $user->business_id ?? $request->integer('business_id') ?: null;

        $methods = PaymentMethod::query()
            ->where('status', 'active')
            ->where(function ($query) use ($businessId) {
                $query->whereNull('business_id');
                if ($businessId !== null) {
                    $query->orWhere('business_id', $businessId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'business_id', 'name', 'slug']);

        return ApiResponse::success($methods, 'Payment methods retrieved successfully.');
    }
}
