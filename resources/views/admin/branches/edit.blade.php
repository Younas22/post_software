<x-layouts.admin :breadcrumbs="[['label' => 'Businesses', 'url' => route('admin.businesses.index')], ['label' => $business->name, 'url' => route('admin.businesses.show', $business)], ['label' => 'Edit Branch']]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Edit Branch — {{ $branch->name }}</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.businesses.branches.update', [$business, $branch]) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.branches._form', ['branch' => $branch])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Save changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
