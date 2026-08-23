<x-layouts.admin :breadcrumbs="[['label' => 'Businesses', 'url' => route('admin.businesses.index')], ['label' => 'New Business']]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">New Business</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.businesses.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @include('admin.businesses._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Create business</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
