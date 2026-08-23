@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-16 text-center']) }}>
    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
