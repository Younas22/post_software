<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Subscriptions']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Subscriptions</h1>
    </div>

    <x-card :padded="false">
        @if ($subscriptions->isEmpty())
            <x-empty-state icon="credit-card" title="No subscriptions yet" description="Client subscriptions will appear here once created." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Package</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Ends</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($subscriptions as $subscription)
                        <tr class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50" onclick="window.location='{{ route('admin.subscriptions.show', $subscription) }}'">
                            <td class="px-4 py-3 text-neutral-900 dark:text-white">{{ $subscription->client->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $subscription->package->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$subscription->statusBadgeVariant()">{{ $subscription->effectiveStatus() }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">
                                {{ $subscription->ends_at?->format('M j, Y') ?? ($subscription->trial_ends_at?->format('M j, Y').' (trial)' ?? '—') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($subscriptions->hasPages())
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    @endif
</x-layouts.admin>
