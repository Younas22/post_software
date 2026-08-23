<x-layouts.app area="Client" :title="$business->name">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $business->name }}</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            {{ $business->businessType->name ?? 'Uncategorized' }}
        </p>
    </div>

    <x-card>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-neutral-500 dark:text-neutral-400">Status</dt>
                <dd class="mt-1 capitalize text-neutral-900 dark:text-white">{{ $business->status }}</dd>
            </div>
            <div>
                <dt class="text-sm text-neutral-500 dark:text-neutral-400">Branches</dt>
                <dd class="mt-1 text-neutral-900 dark:text-white">{{ $business->branches()->count() }}</dd>
            </div>
        </dl>
    </x-card>
</x-layouts.app>
