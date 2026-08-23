{{-- Overrides Laravel's default pagination view (auto-resolved from this
     exact vendor/pagination/tailwind.blade.php path) to match the black /
     white / neutral design system. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            {!! __('Showing') !!}
            <span class="font-medium text-neutral-900 dark:text-white">{{ $paginator->firstItem() }}</span>
            {!! __('to') !!}
            <span class="font-medium text-neutral-900 dark:text-white">{{ $paginator->lastItem() }}</span>
            {!! __('of') !!}
            <span class="font-medium text-neutral-900 dark:text-white">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-300 dark:text-neutral-700">
                    <x-icon name="chevron-right" class="h-4 w-4 rotate-180" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800" rel="prev">
                    <x-icon name="chevron-right" class="h-4 w-4 rotate-180" />
                </a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-9 w-9 items-center justify-center text-sm text-neutral-400 dark:text-neutral-600">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-neutral-900 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800" rel="next">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-300 dark:text-neutral-700">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
