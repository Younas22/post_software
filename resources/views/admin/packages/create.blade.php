<x-layouts.admin :breadcrumbs="[['label' => 'SaaS', 'url' => route('admin.packages.index')], ['label' => 'Packages', 'url' => route('admin.packages.index')], ['label' => 'New Package']]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">New Package</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.packages.store') }}" class="space-y-6">
                @csrf
                @include('admin.packages._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Create package</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
