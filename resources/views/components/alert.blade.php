@props(['variant' => 'info'])

@php
$variants = [
    'info' => 'border-neutral-200 bg-neutral-50 text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/50 dark:text-neutral-300',
    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
    'error' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-md border px-4 py-3 text-sm '.($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
</div>
