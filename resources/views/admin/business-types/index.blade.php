<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Business Types']]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Business Types</h1>
        <x-button :href="route('admin.business-types.create')">
            <x-icon name="plus" class="h-4 w-4" /> New business type
        </x-button>
    </div>

    <x-card :padded="false">
        @if ($businessTypes->isEmpty())
            <x-empty-state icon="tag" title="No business types configured" />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">Businesses</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($businessTypes as $type)
                        <tr>
                            <td class="px-4 py-3 text-neutral-900 dark:text-white">{{ $type->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $type->slug }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $type->businesses_count }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$type->is_active ? 'success' : 'neutral'">{{ $type->is_active ? 'active' : 'inactive' }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.business-types.edit', $type) }}" class="text-sm text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Edit</a>
                                    @if ($type->is_active)
                                        <form method="POST" action="{{ route('admin.business-types.deactivate', $type) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-sm text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.business-types.activate', $type) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-sm text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Activate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($businessTypes->hasPages())
        <div class="mt-4">{{ $businessTypes->links() }}</div>
    @endif
</x-layouts.admin>
