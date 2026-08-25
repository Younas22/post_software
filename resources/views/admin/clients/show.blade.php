@php
    $subscription = $client->currentSubscription;
    $statusVariant = match ($client->status) {
        'active' => 'success',
        'suspended' => 'warning',
        default => 'danger',
    };
@endphp

<x-layouts.admin :breadcrumbs="[['label' => 'Clients', 'url' => route('admin.clients.index')], ['label' => $client->name]]">
    @if (session('generated_password'))
        <x-alert variant="success" class="mb-6">
            <p class="font-medium">Owner account created — send these login details to the client now.</p>
            <p class="mt-1">This password is shown only this once and cannot be retrieved again.</p>
            <dl class="mt-3 space-y-1 font-mono text-sm">
                <div><dt class="inline text-emerald-700/70 dark:text-emerald-400/70">Email:</dt> <dd class="inline select-all">{{ session('generated_owner_email') }}</dd></div>
                <div><dt class="inline text-emerald-700/70 dark:text-emerald-400/70">Password:</dt> <dd class="inline select-all">{{ session('generated_password') }}</dd></div>
            </dl>
        </x-alert>
    @endif

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $client->name }}</h1>
                <x-badge :variant="$statusVariant">{{ $client->status }}</x-badge>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $client->email }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-button variant="secondary" :href="route('admin.clients.edit', $client)">Edit</x-button>

            @if ($client->status === 'active')
                <x-button variant="secondary" data-modal-open="suspend-client-modal">Suspend</x-button>
            @else
                <form method="POST" action="{{ route('admin.clients.activate', $client) }}">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit">Activate</x-button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Overview</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Phone</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $client->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Businesses</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $client->businesses->count() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Subscription</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">
                        {{ $subscription?->package?->name ?? 'No subscription' }}
                        @if ($subscription)
                            <x-badge variant="neutral" class="ml-1">{{ $subscription->status }}</x-badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Created</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $client->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>

            @if ($client->notes)
                <div class="mt-6 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-neutral-700 dark:text-neutral-300">{{ $client->notes }}</dd>
                </div>
            @endif
        </x-card>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Businesses</h2>
                <a href="{{ route('admin.businesses.create') }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">+ Add</a>
            </div>

            @if ($client->businesses->isEmpty())
                <p class="text-sm text-neutral-400 dark:text-neutral-600">No businesses yet.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($client->businesses as $business)
                        <li>
                            <a href="{{ route('admin.businesses.show', $business) }}" class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                <span class="text-neutral-900 dark:text-white">{{ $business->name }}</span>
                                <span class="text-xs text-neutral-400 dark:text-neutral-600">{{ $business->businessType->name ?? '—' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-confirm-dialog
        id="suspend-client-modal"
        title="Suspend this client?"
        description="{{ $client->name }} and all of its users will lose access until reactivated."
        confirm-label="Suspend"
        :action="route('admin.clients.suspend', $client)"
        method="PATCH"
    />
</x-layouts.admin>
