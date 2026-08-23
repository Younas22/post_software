@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 disabled:bg-neutral-100 disabled:text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white']) }}>
    {{ $slot }}
</select>
