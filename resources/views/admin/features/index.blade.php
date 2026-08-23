<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Features']]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Features</h1>
        <x-button :href="route('admin.features.create')">
            <x-icon name="plus" class="h-4 w-4" /> New feature
        </x-button>
    </div>

    <x-card :padded="false">
        @if ($features->isEmpty())
            <x-empty-state icon="puzzle" title="No features yet" description="Features are the building blocks packages are assembled from." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($features as $feature)
                        <tr>
                            <td class="px-4 py-3 text-neutral-900 dark:text-white">{{ $feature->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $feature->slug }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$feature->status === 'active' ? 'success' : 'neutral'">{{ $feature->status }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.features.edit', $feature) }}" class="text-sm text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($features->hasPages())
        <div class="mt-4">{{ $features->links() }}</div>
    @endif
</x-layouts.admin>
