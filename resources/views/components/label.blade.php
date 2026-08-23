@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-1.5 block text-sm font-medium text-neutral-700 dark:text-neutral-300']) }}>
    {{ $value ?? $slot }}
</label>
