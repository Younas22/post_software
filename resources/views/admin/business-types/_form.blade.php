@php $businessType ??= null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="name" value="Name" />
        <x-input id="name" name="name" :value="old('name', $businessType->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="slug" value="Slug" />
        <x-input id="slug" name="slug" :value="old('slug', $businessType->slug ?? '')" placeholder="Auto-generated from name if left blank" />
        <x-input-error :messages="$errors->get('slug')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('description', $businessType->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $businessType->is_active ?? true))
                class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-white">
            Active
        </label>
    </div>
</div>
