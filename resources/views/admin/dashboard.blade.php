<x-layouts.admin :breadcrumbs="[['label' => 'Dashboard']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">
            Welcome, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Platform-wide overview
        </p>
    </div>

    {{-- Company profile — the branding set in System Settings. --}}
    @php($branding = app(\App\Support\Branding::class))
    <x-card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800">
                @if ($branding->logoUrl())
                    <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }} logo" class="h-full w-full object-contain p-1">
                @else
                    <span class="text-xl font-bold text-neutral-400">{{ $branding->initials() }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-base font-semibold text-neutral-900 dark:text-white">{{ $branding->name() }}</p>
                @if ($branding->officeAddress())
                    <p class="mt-1 flex items-start gap-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                        <x-icon name="building" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span class="whitespace-pre-line">{{ $branding->officeAddress() }}</span>
                    </p>
                @endif
                @if ($branding->supportEmail() || $branding->supportPhone())
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ collect([$branding->supportEmail(), $branding->supportPhone()])->filter()->join(' · ') }}
                    </p>
                @endif
            </div>
            @can('update', \App\Models\Setting::class)
                <a href="{{ route('admin.settings.index') }}" class="shrink-0 text-sm font-medium text-neutral-600 underline-offset-4 hover:text-neutral-900 hover:underline dark:text-neutral-400 dark:hover:text-white">Edit branding</a>
            @endcan
        </div>
    </x-card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Clients" :value="$totalClients" icon="users" :href="route('admin.clients.index')" />
        <x-stat-card label="Active Clients" :value="$activeClients" icon="check-circle" />
        <x-stat-card label="Trial Clients" :value="$trialClients" icon="credit-card" />
        <x-stat-card label="Expired Clients" :value="$expiredClients" icon="x-circle" />
        <x-stat-card label="Businesses" :value="$businessCount" icon="building" :href="route('admin.businesses.index')" />
        <x-stat-card label="Subscriptions" :value="$subscriptionCount" icon="credit-card" :href="route('admin.subscriptions.index')" />
        <x-stat-card label="Devices" :value="$deviceCount" icon="monitor" :href="route('admin.devices.index')" />
    </div>
</x-layouts.admin>
