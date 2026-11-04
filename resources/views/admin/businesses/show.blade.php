<x-layouts.admin :breadcrumbs="[['label' => 'Businesses', 'url' => route('admin.businesses.index')], ['label' => $business->name]]">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            @if ($business->logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($business->logo) }}" alt="" class="h-12 w-12 rounded-md border border-neutral-200 object-cover dark:border-neutral-800">
            @endif
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $business->name }}</h1>
                    <x-badge :variant="$business->status === 'active' ? 'success' : 'neutral'">{{ $business->status }}</x-badge>
                </div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ $business->client->name ?? '—' }} &middot; {{ $business->businessType->name ?? 'Uncategorized' }}
                </p>
            </div>
        </div>

        <x-button variant="secondary" :href="route('admin.businesses.edit', $business)">Edit</x-button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Overview</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Phone</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $business->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Email</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $business->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Currency</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $business->currency }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Timezone</dt>
                    <dd class="mt-1 text-neutral-900 dark:text-white">{{ $business->timezone }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Address</dt>
                    <dd class="mt-1 whitespace-pre-line text-neutral-900 dark:text-white">{{ $business->address ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Branches</h2>
                <a href="{{ route('admin.businesses.branches.create', $business) }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">+ Add</a>
            </div>

            @if ($business->branches->isEmpty())
                <p class="text-sm text-neutral-400 dark:text-neutral-600">No branches yet.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($business->branches as $branch)
                        <li class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <a href="{{ route('admin.businesses.branches.edit', [$business, $branch]) }}" class="min-w-0 flex-1 text-neutral-900 dark:text-white">
                                {{ $branch->name }}
                                @if ($branch->is_main)
                                    <span class="text-xs text-neutral-400 dark:text-neutral-600">(main)</span>
                                @endif
                            </a>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-badge :variant="$branch->status === 'active' ? 'success' : 'neutral'">{{ $branch->status }}</x-badge>
                                @if ($branch->status === 'active')
                                    <form method="POST" action="{{ route('admin.businesses.branches.deactivate', [$business, $branch]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Deactivate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.businesses.branches.activate', [$business, $branch]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Activate</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card class="mt-4">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-600">Settings</h2>
        <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
            Business-level configuration — falls back to the platform default when unset.
        </p>

        <form method="POST" action="{{ route('admin.businesses.settings.update', $business) }}" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            @csrf
            @method('PUT')

            @foreach ($businessSettingKeys as $key => $definition)
                <div>
                    <x-label :for="$key" :value="\Illuminate\Support\Str::of($key)->replace('_', ' ')->title()" />
                    @if ($definition['type'] === 'boolean')
                        <x-select :id="$key" :name="$key">
                            <option value="1" @selected($businessSettingValues[$key])>Yes</option>
                            <option value="0" @selected(! $businessSettingValues[$key])>No</option>
                        </x-select>
                    @else
                        <x-input :id="$key" :name="$key" :value="$businessSettingValues[$key]" />
                    @endif
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <x-button type="submit" variant="secondary">Save settings</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.admin>
