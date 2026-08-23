@php $user = auth()->user(); @endphp

<x-layouts.app area="Client" :title="'Dashboard'">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">
            Welcome, {{ $user->name }}
        </h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            {{ $user->roles->pluck('name')->implode(', ') ?: 'No role assigned' }}
        </p>
    </div>

    <x-card>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Your business</p>
        @if ($user->business)
            <p class="mt-1 text-lg font-medium text-neutral-900 dark:text-white">
                {{ $user->business->name }}
            </p>
            <a href="{{ route('client.businesses.show', $user->business) }}" class="mt-3 inline-block text-sm underline hover:text-neutral-900 dark:hover:text-white">
                View business
            </a>
        @else
            <p class="mt-1 text-neutral-500 dark:text-neutral-400">No business assigned yet.</p>
        @endif
    </x-card>

    <p class="mt-8 text-sm text-neutral-400 dark:text-neutral-600">
        This is a placeholder landing page — the full client dashboard is a later phase.
    </p>
</x-layouts.app>
