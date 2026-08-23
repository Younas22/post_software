<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PackageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Staff management for a client's own business(es) — a Client Owner or
 * Business Admin manages their team here. Every write is tenant-scoped
 * against the AUTHENTICATED actor's own client (never a value the request
 * supplies), except for a Super Admin, who has no client of their own and
 * so must name one explicitly (see StoreEmployeeRequest).
 */
class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $clientId = $actor->is_super_admin ? $request->integer('client_id') : $actor->client_id;

        $search = trim((string) $request->query('search', ''));

        $employees = User::query()
            ->where('client_id', $clientId)
            ->with(['roles', 'business', 'branch'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        $employees->getCollection()->transform(fn (User $employee) => $this->payload($employee));

        return ApiResponse::collection($employees, 'Employees retrieved successfully.');
    }

    public function store(StoreEmployeeRequest $request, PackageLimitService $limits, ActivityLogger $logger): JsonResponse
    {
        $actor = $request->user();
        $clientId = $actor->is_super_admin ? $request->integer('client_id') : $actor->client_id;
        $client = Client::query()->findOrFail($clientId);

        if (! $limits->canCreateUser($client)) {
            $limit = $limits->maxUsers($client);

            return ApiResponse::error(
                "This client's package allows at most {$limit} user(s). Deactivate a user or upgrade the package to add another.",
                null,
                403,
            );
        }

        $employee = new User();
        $employee->forceFill([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'status' => 'active',
            'client_id' => $client->id,
            'business_id' => $request->integer('business_id') ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'email_verified_at' => now(),
        ])->save();

        $role = Role::query()->where('slug', $request->string('role'))->firstOrFail();
        $employee->roles()->sync([$role->id]);

        $logger->log('employee.created', $employee, "Employee \"{$employee->name}\" created", actor: $actor);

        return ApiResponse::success($this->payload($employee->fresh(['roles', 'business', 'branch'])), 'Employee created successfully.', 201);
    }

    public function show(User $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return ApiResponse::success($this->payload($employee->load(['roles', 'business', 'branch'])), 'Employee retrieved successfully.');
    }

    public function update(UpdateEmployeeRequest $request, User $employee, ActivityLogger $logger): JsonResponse
    {
        $employee->fill($request->safe()->only(['name', 'email']));

        if ($request->filled('password')) {
            $employee->password = Hash::make($request->string('password'));
        }

        // business_id/branch_id are deliberately excluded from User::$fillable
        // (Phase 9) — forceFill is used here only after UpdateEmployeeRequest
        // has already verified both belong to this employee's own client.
        if ($request->has('business_id')) {
            $employee->forceFill(['business_id' => $request->integer('business_id') ?: null]);
        }
        if ($request->has('branch_id')) {
            $employee->forceFill(['branch_id' => $request->integer('branch_id') ?: null]);
        }

        $employee->save();

        if ($request->filled('role')) {
            $role = Role::query()->where('slug', $request->string('role'))->firstOrFail();
            $employee->roles()->sync([$role->id]);
        }

        $logger->log('employee.updated', $employee, "Employee \"{$employee->name}\" updated", actor: $request->user());

        return ApiResponse::success($this->payload($employee->fresh(['roles', 'business', 'branch'])), 'Employee updated successfully.');
    }

    public function activate(Request $request, User $employee, ActivityLogger $logger): JsonResponse
    {
        $this->authorize('update', $employee);

        $limits = app(PackageLimitService::class);

        if (! $limits->canCreateUser($employee->client, excluding: $employee)) {
            $limit = $limits->maxUsers($employee->client);

            return ApiResponse::error(
                "This client's package allows at most {$limit} active user(s).",
                null,
                403,
            );
        }

        $employee->update(['status' => 'active']);

        $logger->log('employee.activated', $employee, "Employee \"{$employee->name}\" activated", actor: $request->user());

        return ApiResponse::success($this->payload($employee), 'Employee activated successfully.');
    }

    public function deactivate(Request $request, User $employee, ActivityLogger $logger): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee->update(['status' => 'inactive']);

        $logger->log('employee.deactivated', $employee, "Employee \"{$employee->name}\" deactivated", actor: $request->user());

        return ApiResponse::success($this->payload($employee), 'Employee deactivated successfully.');
    }

    /**
     * A deliberately explicit payload — never a raw model dump, and never
     * includes the password hash (already excluded via User::$hidden, but
     * this whitelist is a second, independent guarantee).
     *
     * @return array<string, mixed>
     */
    private function payload(User $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'status' => $employee->status,
            'client_id' => $employee->client_id,
            'business_id' => $employee->business_id,
            'branch_id' => $employee->branch_id,
            'business' => $employee->business ? ['id' => $employee->business->id, 'name' => $employee->business->name] : null,
            'branch' => $employee->branch ? ['id' => $employee->branch->id, 'name' => $employee->branch->name] : null,
            'roles' => $employee->roles->pluck('slug'),
            'created_at' => $employee->created_at,
        ];
    }
}
