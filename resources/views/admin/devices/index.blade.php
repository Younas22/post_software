@php
    $variant = fn ($status) => match ($status) {
        'active' => 'success',
        'blocked' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Devices']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Devices</h1>
    </div>

    <x-card :padded="false">
        @if ($devices->isEmpty())
            <x-empty-state icon="monitor" title="No devices registered" description="POS devices activated by clients will appear here." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Platform</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Last seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($devices as $device)
                        <tr class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50" onclick="window.location='{{ route('admin.devices.show', $device) }}'">
                            <td class="px-4 py-3 text-neutral-900 dark:text-white">{{ $device->name }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $device->client->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $device->branch->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 capitalize">{{ $device->platform }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$variant($device->status)">{{ $device->status }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">
                                {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($devices->hasPages())
        <div class="mt-4">{{ $devices->links() }}</div>
    @endif
</x-layouts.admin>
