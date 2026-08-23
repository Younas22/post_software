<x-layouts.admin :breadcrumbs="[['label' => 'System', 'url' => route('admin.activity-logs.index')], ['label' => 'Activity Logs']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Activity Logs</h1>
    </div>

    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
            <x-input type="text" name="search" value="{{ $search }}" placeholder="Search description or action…" class="pl-9" />
        </div>
        <x-select name="action" class="sm:w-56" onchange="this.form.submit()">
            <option value="" @selected($action === '')>All actions</option>
            @foreach (\App\Models\ActivityLog::ACTIONS as $option)
                <option value="{{ $option }}" @selected($action === $option)>{{ ucfirst(str_replace(['.', '_'], ' ', $option)) }}</option>
            @endforeach
        </x-select>
        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <x-card :padded="false">
        @if ($logs->isEmpty())
            <x-empty-state icon="clipboard-list" title="No activity recorded yet" description="An audit trail of platform actions will appear here." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Action</th>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Entity</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium">IP</th>
                        <th class="px-4 py-3 font-medium">Date/Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-4 py-3">
                                <x-badge variant="neutral">{{ $log->action }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $log->user->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $log->entityLabel() }}</td>
                            <td class="px-4 py-3 text-neutral-900 dark:text-white">{{ $log->description ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $log->ip_address ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $log->created_at->format('M j, Y g:ia') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($logs->hasPages())
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-layouts.admin>
