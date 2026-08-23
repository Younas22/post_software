<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Business Types', 'url' => route('admin.business-types.index')], ['label' => 'Edit '.$businessType->name]]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Edit Business Type</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.business-types.update', $businessType) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.business-types._form', ['businessType' => $businessType])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Save changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
