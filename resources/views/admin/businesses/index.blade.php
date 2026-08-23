<x-layouts.admin :breadcrumbs="[['label' => 'Businesses']]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Businesses</h1>
        <x-button :href="route('admin.businesses.create')">
            <x-icon name="plus" class="h-4 w-4" /> New business
        </x-button>
    </div>

    <form method="GET" action="{{ route('admin.businesses.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
            <x-input type="text" name="search" value="{{ $search }}" placeholder="Search by name…" class="pl-9" />
        </div>
        <x-select name="status" class="sm:w-48" onchange="this.form.submit()">
            <option value="" @selected($status === '')>All statuses</option>
            @foreach (['active', 'inactive'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <x-card :padded="false">
        @if ($businesses->isEmpty())
            <x-empty-state icon="building" title="No businesses found" description="Try a different search or filter, or create a new business." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($businesses as $business)
                        <tr class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50" onclick="window.location='{{ route('admin.businesses.show', $business) }}'">
                            <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $business->name }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $business->client->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $business->businessType->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$business->status === 'active' ? 'success' : 'neutral'">{{ $business->status }}</x-badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($businesses->hasPages())
        <div class="mt-4">{{ $businesses->links() }}</div>
    @endif
</x-layouts.admin>
