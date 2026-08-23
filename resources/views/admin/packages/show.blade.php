<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Packages', 'url' => route('admin.packages.index')], ['label' => $package->name]]">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $package->name }}</h1>
                <x-badge :variant="$package->status === 'active' ? 'success' : 'neutral'">{{ $package->status }}</x-badge>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                ${{ number_format($package->price, 2) }} / {{ $package->billing_cycle }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-button variant="secondary" :href="route('admin.packages.edit', $package)">Edit</x-button>

            @if ($package->status === 'active')
                <form method="POST" action="{{ route('admin.packages.deactivate', $package) }}">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" variant="secondary">Deactivate</x-button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.packages.activate', $package) }}">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit">Activate</x-button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Limits</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Max businesses</dt><dd class="text-neutral-900 dark:text-white">{{ $package->max_businesses ?? '∞' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Max branches</dt><dd class="text-neutral-900 dark:text-white">{{ $package->max_branches ?? '∞' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Max users</dt><dd class="text-neutral-900 dark:text-white">{{ $package->max_users ?? '∞' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Max devices</dt><dd class="text-neutral-900 dark:text-white">{{ $package->max_devices ?? '∞' }}</dd></div>
            </dl>
        </x-card>

        <x-card class="lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Features</h2>
            @if ($package->features->isEmpty())
                <p class="text-sm text-neutral-400 dark:text-neutral-600">No features assigned.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($package->features as $feature)
                        <x-badge variant="neutral">{{ $feature->name }}</x-badge>
                    @endforeach
                </div>
            @endif

            @if ($package->description)
                <p class="mt-6 border-t border-neutral-100 pt-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-400">
                    {{ $package->description }}
                </p>
            @endif
        </x-card>
    </div>
</x-layouts.admin>
