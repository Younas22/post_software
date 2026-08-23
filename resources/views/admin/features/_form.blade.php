@php $feature ??= null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="name" value="Name" />
        <x-input id="name" name="name" :value="old('name', $feature->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="slug" value="Slug" />
        <x-input id="slug" name="slug" :value="old('slug', $feature->slug ?? '')" placeholder="Auto-generated from name if left blank" />
        <x-input-error :messages="$errors->get('slug')" />
    </div>

    <div>
        <x-label for="status" value="Status" />
        <x-select id="status" name="status">
            @foreach (['active', 'inactive'] as $option)
                <option value="{{ $option }}" @selected(old('status', $feature->status ?? 'active') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('description', $feature->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>
</div>
