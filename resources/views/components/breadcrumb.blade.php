@props(['items' => []])

<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex min-w-0 items-center gap-1.5 text-sm">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-1.5">
                @if (!$loop->first)
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-neutral-300 dark:text-neutral-700" />
                @endif
                @if (!empty($item['url']) && !$loop->last)
                    <a href="{{ $item['url'] }}" class="truncate text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">{{ $item['label'] }}</a>
                @else
                    <span class="truncate font-semibold text-neutral-900 dark:text-white">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
