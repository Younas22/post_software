<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Features', 'url' => route('admin.features.index')], ['label' => 'Edit '.$feature->name]]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Edit Feature</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.features.update', $feature) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.features._form', ['feature' => $feature])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Save changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
