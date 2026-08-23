@props(['variant' => 'primary', 'href' => null])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';

$variants = [
    'primary' => 'bg-neutral-900 text-white hover:bg-neutral-700 focus-visible:ring-neutral-900 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200 dark:focus-visible:ring-white',
    'secondary' => 'bg-transparent text-neutral-900 border border-neutral-300 hover:bg-neutral-100 focus-visible:ring-neutral-400 dark:text-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800',
    'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600',
];

$classes = $base.' '.($variants[$variant] ?? $variants['primary']).' w-full sm:w-auto';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
