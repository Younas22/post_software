@props(['variant' => 'neutral'])

@php
// Deliberate, minimal use of semantic color — reserved for status meaning
// only (never decoration), against the black/white/neutral brand chrome.
$variants = [
    'neutral' => 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400',
    'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
    'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
