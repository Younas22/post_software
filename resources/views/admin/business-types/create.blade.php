<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Business Types', 'url' => route('admin.business-types.index')], ['label' => 'New Business Type']]">
    <div class="mx-auto max-w-5xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">New Business Type</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.business-types.store') }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @include('admin.business-types._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Create business type</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
