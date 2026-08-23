@props(['align' => 'right'])

<div class="relative" data-dropdown>
    <div data-dropdown-trigger class="cursor-pointer">
        {{ $trigger }}
    </div>

    <div
        data-dropdown-menu
        class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-20 mt-2 hidden w-56 rounded-md border border-neutral-200 bg-white py-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        {{ $slot }}
    </div>
</div>
