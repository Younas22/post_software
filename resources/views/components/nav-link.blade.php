@props(['href', 'icon', 'active' => false])

<a
    href="{{ $href }}"
    title="{{ $slot }}"
    class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors {{ $active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white' }}"
>
    <x-icon :name="$icon" class="h-5 w-5 shrink-0" />
    <span data-sidebar-label class="truncate">{{ $slot }}</span>
</a>
