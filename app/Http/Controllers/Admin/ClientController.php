<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $clients = Client::query()
            ->withCount('businesses')
            ->with('currentSubscription.package')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'suspended', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.clients.index', [
            'clients' => $clients,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('admin.clients.create', [
            'packages' => Package::query()->where('status', 'active')->orderBy('price')->get(),
        ]);
    }

    /**
     * Creates the Client together with its first login (a Client Owner
     * user) and, optionally, an initial subscription — a bare Client row
     * with no owner and no subscription is not something the admin who
     * asked for this could actually hand off: the owner can't even reach
     * the client dashboard without an active subscription (see
     * routes/client.php's 'subscription.active' middleware), so leaving
     * either step out would just move the same problem one screen later.
     */
    public function store(StoreClientRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $generatedPassword = Str::password(14);

        [$client, $owner] = DB::transaction(function () use ($request, $generatedPassword) {
            $client = Client::query()->create($request->safe()->only(['name', 'email', 'phone', 'status', 'notes']));

            // is_super_admin/client_id/business_id/branch_id are deliberately
            // excluded from User::$fillable — set here via forceFill, same
            // pattern as DemoClientSeeder and EmployeeController::store().
            $owner = new User();
            $owner->forceFill([
                'name' => $request->string('owner_name'),
                'email' => $request->string('owner_email'),
                'password' => Hash::make($generatedPassword),
                'status' => 'active',
                'client_id' => $client->id,
                'email_verified_at' => now(),
            ])->save();

            $ownerRole = Role::query()->where('slug', 'client-owner')->first();
            if ($ownerRole) {
                $owner->roles()->attach($ownerRole);
            }

            if ($request->filled('package_id')) {
                $package = Package::query()->findOrFail($request->integer('package_id'));
                Subscription::query()->create([
                    'client_id' => $client->id,
                    'package_id' => $package->id,
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays(14),
                    'starts_at' => now(),
                ]);
            }

            return [$client, $owner];
        });

        $logger->log('client.created', $client, "Client \"{$client->name}\" created");
        $logger->log('employee.created', $owner, "Owner account created for \"{$client->name}\"");

        return redirect()->route('admin.clients.show', $client)
            ->with('status', 'Client created.')
            ->with('generated_owner_email', $request->string('owner_email')->value())
            ->with('generated_password', $generatedPassword);
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        $client->load(['businesses.businessType', 'currentSubscription.package']);

        return view('admin.clients.show', ['client' => $client]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('admin.clients.edit', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client, ActivityLogger $logger): RedirectResponse
    {
        $client->update($request->validated());

        $logger->log('client.updated', $client, "Client \"{$client->name}\" updated");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client updated.');
    }

    public function suspend(Client $client, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update(['status' => 'suspended']);

        $logger->log('client.suspended', $client, "Client \"{$client->name}\" suspended");

        return back()->with('status', 'Client suspended.');
    }

    public function activate(Client $client, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update(['status' => 'active']);

        $logger->log('client.activated', $client, "Client \"{$client->name}\" activated");

        return back()->with('status', 'Client activated.');
    }
}
