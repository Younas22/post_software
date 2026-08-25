<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBranchRequest;
use App\Http\Requests\Api\V1\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Services\PackageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token-authed Branch management — previously Admin-Blade-only (see
 * docs/LARAVEL_API_DISCOVERY.md §13/§21). Reuses BranchPolicy/
 * PackageLimitService as-is; nothing about tenant scoping or the
 * max_branches limit is reimplemented here.
 */
class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $actor = $request->user();

        $branches = Branch::query()
            ->whereHas('business', function ($query) use ($actor) {
                $query->where('client_id', $actor->client_id);
                if ($actor->business_id !== null) {
                    $query->where('id', $actor->business_id);
                }
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->get();

        return ApiResponse::success($branches, 'Branches retrieved successfully.');
    }

    public function store(StoreBranchRequest $request, PackageLimitService $limits): JsonResponse
    {
        $business = Business::query()->findOrFail($request->user()->defaultBusinessId() ?? $request->integer('business_id'));
        $this->authorize('create', [Branch::class, $business]);

        $client = $business->client;
        if (! $limits->canCreateBranch($client)) {
            $limit = $limits->maxBranches($client);

            return ApiResponse::error("This client's package allows at most {$limit} branch(es). Upgrade the package to add another.", null, 403);
        }

        $branch = $business->branches()->create($request->safe()->only(['name', 'code', 'address', 'phone', 'is_main', 'status']));

        return ApiResponse::success($branch, 'Branch created successfully.', 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return ApiResponse::success($branch, 'Branch retrieved successfully.');
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->safe()->only(['name', 'code', 'address', 'phone', 'is_main', 'status']));

        return ApiResponse::success($branch, 'Branch updated successfully.');
    }

    public function activate(Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $branch->update(['status' => 'active']);

        return ApiResponse::success($branch, 'Branch activated successfully.');
    }

    public function deactivate(Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $branch->update(['status' => 'inactive']);

        return ApiResponse::success($branch, 'Branch deactivated successfully.');
    }
}
