<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Subscriptions', 'url' => route('admin.subscriptions.index')], ['label' => $subscription->client->name ?? 'Subscription']]">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $subscription->client->name ?? '—' }}</h1>
                <x-badge :variant="$subscription->statusBadgeVariant()">{{ $subscription->effectiveStatus() }}</x-badge>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $subscription->package->name ?? '—' }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($subscription->status !== 'active')
                <form method="POST" action="{{ route('admin.subscriptions.activate', $subscription) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit">Activate</x-button>
                </form>
            @endif

            @if (!in_array($subscription->status, ['suspended', 'cancelled']))
                <form method="POST" action="{{ route('admin.subscriptions.suspend', $subscription) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="secondary">Suspend</x-button>
                </form>
            @endif

            @if ($subscription->status !== 'cancelled')
                <x-button variant="danger" data-modal-open="cancel-subscription-modal">Cancel</x-button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Overview</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Stored status</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->status }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Effective status</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->effectiveStatus() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Starts</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->starts_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Ends</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->ends_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Trial ends</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->trial_ends_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Cancelled at</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $subscription->cancelled_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="space-y-4">
            <x-card>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Extend</h2>
                <form method="POST" action="{{ route('admin.subscriptions.extend', $subscription) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <x-input type="date" name="ends_at" required min="{{ now()->addDay()->toDateString() }}" />
                    <x-input-error :messages="$errors->get('ends_at')" />
                    <x-button type="submit" class="w-full">Extend subscription</x-button>
                </form>
            </x-card>

            <x-card>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Change Package</h2>
                <form method="POST" action="{{ route('admin.subscriptions.change-package', $subscription) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <x-select name="package_id" required>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected($package->id === $subscription->package_id)>{{ $package->name }} ({{ app(\App\Support\PlatformCurrency::class)->format($package->price) }})</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('package_id')" />
                    <x-button type="submit" variant="secondary" class="w-full">Change package</x-button>
                </form>
            </x-card>
        </div>
    </div>

    <x-confirm-dialog
        id="cancel-subscription-modal"
        title="Cancel this subscription?"
        description="{{ $subscription->client->name ?? 'This client' }} will lose access to their current plan immediately."
        confirm-label="Cancel subscription"
        :action="route('admin.subscriptions.cancel', $subscription)"
        method="PATCH"
    />
</x-layouts.admin>
