{{-- A KPI tile: label + real count + an icon chip for quick scanning. No
     trend/percentage-change indicator is shown — that would require
     historical time-series data this system doesn't collect yet, and a
     fabricated delta would violate "no fake analytics". --}}
@props(['label', 'value', 'icon' => 'inbox', 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900'.($href ? ' transition-colors hover:border-neutral-300 dark:hover:border-neutral-700' : '')]) }}
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm text-neutral-500 dark:text-neutral-400">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-neutral-900 dark:text-white">{{ $value }}</p>
        </div>
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    </div>
</{{ $tag }}>
