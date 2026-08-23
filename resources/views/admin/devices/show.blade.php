@php
    $variant = match ($device->status) {
        'active' => 'success',
        'blocked' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Devices', 'url' => route('admin.devices.index')], ['label' => $device->name]]">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $device->name }}</h1>
                <x-badge :variant="$variant">{{ $device->status }}</x-badge>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $device->client->name ?? '—' }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($device->status !== 'active')
                <form method="POST" action="{{ route('admin.devices.activate', $device) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit">Activate</x-button>
                </form>
            @endif

            @if ($device->status !== 'inactive')
                <form method="POST" action="{{ route('admin.devices.deactivate', $device) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="secondary">Deactivate</x-button>
                </form>
            @endif

            @if ($device->status !== 'blocked')
                <x-button variant="danger" data-modal-open="block-device-modal">Block</x-button>
            @endif
        </div>
    </div>

    @error('device')
        <x-alert variant="error" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Overview</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Device identifier</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-neutral-900 dark:text-white">{{ $device->device_identifier }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Platform</dt>
                    <dd class="mt-1 capitalize text-neutral-900 dark:text-white">{{ $device->platform }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Business</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $device->business->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Branch</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $device->branch->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Activated</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $device->activated_at?->format('M j, Y g:ia') ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Last seen</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Device Limit</h2>
            <p class="text-2xl font-semibold text-neutral-900 dark:text-white">
                {{ $activeCount }} <span class="text-base font-normal text-neutral-400 dark:text-neutral-600">/ {{ $deviceLimit ?? '∞' }}</span>
            </p>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">active devices on this client's package</p>
        </x-card>
    </div>

    <x-confirm-dialog
        id="block-device-modal"
        title="Block this device?"
        description="{{ $device->name }} will be unable to sync or authenticate until unblocked."
        confirm-label="Block device"
        :action="route('admin.devices.block', $device)"
        method="PATCH"
    />
</x-layouts.admin>
