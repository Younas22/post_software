<x-layouts.admin :breadcrumbs="[['label' => 'Clients', 'url' => route('admin.clients.index')], ['label' => $client->name, 'url' => route('admin.clients.show', $client)], ['label' => 'Edit']]">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Edit Client</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.clients._form', ['client' => $client])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button variant="secondary" type="button" onclick="history.back()">Cancel</x-button>
                    <x-button type="submit">Save changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
