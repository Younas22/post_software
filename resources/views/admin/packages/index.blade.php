<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Packages']]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Packages</h1>
        <x-button :href="route('admin.packages.create')">
            <x-icon name="plus" class="h-4 w-4" /> New package
        </x-button>
    </div>

    @if ($packages->isEmpty())
        <x-card>
            <x-empty-state icon="box" title="No packages yet" description="Packages define what a client's subscription includes." />
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($packages as $package)
                <a href="{{ route('admin.packages.show', $package) }}" class="block">
                    <x-card class="h-full transition-colors hover:border-neutral-300 dark:hover:border-neutral-700">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-semibold text-neutral-900 dark:text-white">{{ $package->name }}</h2>
                            <x-badge :variant="$package->status === 'active' ? 'success' : 'neutral'">{{ $package->status }}</x-badge>
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-white">
                            ${{ number_format($package->price, 2) }}
                            <span class="text-sm font-normal text-neutral-400 dark:text-neutral-600">/{{ $package->billing_cycle }}</span>
                        </p>
                        <dl class="mt-4 space-y-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                            <div class="flex justify-between"><dt>Max businesses</dt><dd>{{ $package->max_businesses ?? '∞' }}</dd></div>
                            <div class="flex justify-between"><dt>Max branches</dt><dd>{{ $package->max_branches ?? '∞' }}</dd></div>
                            <div class="flex justify-between"><dt>Max users</dt><dd>{{ $package->max_users ?? '∞' }}</dd></div>
                            <div class="flex justify-between"><dt>Max devices</dt><dd>{{ $package->max_devices ?? '∞' }}</dd></div>
                        </dl>
                        <p class="mt-4 border-t border-neutral-100 pt-3 text-xs text-neutral-400 dark:border-neutral-800 dark:text-neutral-600">
                            {{ $package->features_count }} {{ \Illuminate\Support\Str::plural('feature', $package->features_count) }}
                        </p>
                    </x-card>
                </a>
            @endforeach
        </div>
    @endif

    @if ($packages->hasPages())
        <div class="mt-4">{{ $packages->links() }}</div>
    @endif
</x-layouts.admin>
