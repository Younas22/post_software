@props([
    'id',
    'title' => 'Are you sure?',
    'description' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'action' => null,
    'method' => 'POST',
    'variant' => 'danger',
])

<x-modal :id="$id" :title="$title">
    @if ($description)
        <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">{{ $description }}</p>
    @endif

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <x-button type="button" variant="secondary" data-modal-close>{{ $cancelLabel }}</x-button>

        @if ($action)
            <form method="POST" action="{{ $action }}">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif
                <x-button type="submit" :variant="$variant" class="w-full sm:w-auto">{{ $confirmLabel }}</x-button>
            </form>
        @else
            {{ $slot }}
        @endif
    </div>
</x-modal>
