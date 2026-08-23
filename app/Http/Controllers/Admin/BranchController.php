<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Services\ActivityLogger;
use App\Services\PackageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function create(Business $business): View
    {
        $this->authorize('create', [Branch::class, $business]);

        return view('admin.branches.create', ['business' => $business]);
    }

    public function store(StoreBranchRequest $request, Business $business, PackageLimitService $limits, ActivityLogger $logger): RedirectResponse
    {
        $client = $business->client;

        if (! $limits->canCreateBranch($client)) {
            $limit = $limits->maxBranches($client);

            return back()->withErrors([
                'branch' => "This client's package allows at most {$limit} branch(es). Upgrade the package to add another.",
            ])->withInput();
        }

        $branch = $business->branches()->create($request->validated());

        $logger->log('branch.created', $branch, "Branch \"{$branch->name}\" created");

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Branch created.');
    }

    public function edit(Business $business, Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('admin.branches.edit', ['business' => $business, 'branch' => $branch]);
    }

    public function update(UpdateBranchRequest $request, Business $business, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Branch updated.');
    }

    public function activate(Business $business, Branch $branch, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update(['status' => 'active']);

        $logger->log('branch.activated', $branch, "Branch \"{$branch->name}\" activated");

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Branch activated.');
    }

    public function deactivate(Business $business, Branch $branch, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update(['status' => 'inactive']);

        $logger->log('branch.deactivated', $branch, "Branch \"{$branch->name}\" deactivated");

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Branch deactivated.');
    }
}
