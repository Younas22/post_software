<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Packages', 'url' => route('admin.packages.index')], ['label' => 'Edit '.$package->name]]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Edit Package</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.packages._form', ['package' => $package])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Save changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
