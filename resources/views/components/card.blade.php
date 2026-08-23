@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900 '.($padded ? 'p-6' : '')]) }}>
    {{ $slot }}
</div>
