<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('admin.clients.create');
    }

    public function store(StoreClientRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $client = Client::query()->create($request->validated());

        $logger->log('client.created', $client, "Client \"{$client->name}\" created");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client created.');
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
