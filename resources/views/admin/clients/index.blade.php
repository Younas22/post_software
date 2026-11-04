<x-layouts.admin :breadcrumbs="[['label' => 'Clients']]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">Clients</h1>
        <x-button :href="route('admin.clients.create')">
            <x-icon name="plus" class="h-4 w-4" /> New client
        </x-button>
    </div>

    <form method="GET" action="{{ route('admin.clients.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
            <x-input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email…" class="pl-9" />
        </div>
        <x-select name="status" class="sm:w-48" onchange="this.form.submit()">
            <option value="" @selected($status === '')>All statuses</option>
            @foreach (['active', 'suspended', 'cancelled'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <x-card :padded="false">
        @if ($clients->isEmpty())
            <x-empty-state icon="users" title="No clients found" description="Try a different search or filter, or create a new client." />
        @else
            <x-table>
                <thead class="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Business (type)</th>
                        <th class="px-4 py-3 font-medium">Login</th>
                        <th class="px-4 py-3 font-medium">Subscription</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($clients as $client)
                        <tr class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50" onclick="window.location='{{ route('admin.clients.show', $client) }}'">
                            <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $client->name }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $client->email }}</td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">
                                @forelse ($client->businesses as $business)
                                    <div class="whitespace-nowrap">
                                        <span class="text-neutral-900 dark:text-white">{{ $business->name }}</span>
                                        <span class="text-xs">({{ $business->businessType?->name ?? '—' }})</span>
                                    </div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-xs" onclick="event.stopPropagation()">
                                @php($login = $logins[$client->id] ?? null)
                                @if ($login && $login['email'])
                                    <div class="font-mono text-neutral-900 dark:text-white">{{ $login['email'] }}</div>
                                    @if ($login['password'])
                                        <div class="mt-0.5 font-mono text-neutral-500 dark:text-neutral-400">
                                            Password: <span class="select-all font-semibold text-neutral-900 dark:text-white">{{ $login['password'] }}</span>
                                        </div>
                                    @else
                                        <div class="mt-0.5 text-neutral-400 dark:text-neutral-500" title="Passwords are stored encrypted (hashed) and cannot be displayed.">Password: custom (hidden)</div>
                                    @endif
                                @else
                                    <span class="text-neutral-400">No login user</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $client->currentSubscription?->package?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$client->status === 'active' ? 'success' : ($client->status === 'suspended' ? 'warning' : 'danger')">
                                    {{ $client->status }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ $client->created_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    @if ($clients->hasPages())
        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
</x-layouts.admin>
