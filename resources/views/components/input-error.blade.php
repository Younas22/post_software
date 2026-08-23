@props(['messages'])

@if ($messages)
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-sm text-red-600 dark:text-red-400']) }}>
        {{ (is_array($messages) || $messages instanceof \Illuminate\Support\MessageBag) ? implode(' ', $messages instanceof \Illuminate\Support\MessageBag ? $messages->all() : $messages) : $messages }}
    </p>
@endif
