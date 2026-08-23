@props(['id', 'title' => null])

<div id="{{ $id }}" data-modal class="fixed inset-0 z-[60] hidden items-center justify-center p-4">
    <div data-modal-backdrop class="absolute inset-0 bg-black/50"></div>

    <div class="relative w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
        <div class="mb-4 flex items-start justify-between gap-4">
            @if ($title)
                <h2 class="text-base font-semibold text-neutral-900 dark:text-white">{{ $title }}</h2>
            @endif
            <button type="button" data-modal-close class="ml-auto shrink-0 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        {{ $slot }}
    </div>
</div>
