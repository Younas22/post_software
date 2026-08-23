<x-layouts.admin :breadcrumbs="[['label' => 'Dashboard']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">
            Welcome, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Platform-wide overview
        </p>
    </div>

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
