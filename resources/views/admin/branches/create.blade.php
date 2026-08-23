<x-layouts.admin :breadcrumbs="[['label' => 'Businesses', 'url' => route('admin.businesses.index')], ['label' => $business->name, 'url' => route('admin.businesses.show', $business)], ['label' => 'New Branch']]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">New Branch — {{ $business->name }}</h1>

        @error('branch')
            <x-alert variant="error" class="mb-4">{{ $message }}</x-alert>
        @enderror

        <x-card>
            <form method="POST" action="{{ route('admin.businesses.branches.store', $business) }}" class="space-y-6">
                @csrf
                @include('admin.branches._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Create branch</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
