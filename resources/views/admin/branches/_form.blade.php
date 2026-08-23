@php $branch ??= null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="name" value="Name" />
        <x-input id="name" name="name" :value="old('name', $branch->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" name="phone" :value="old('phone', $branch->phone ?? '')" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>

    <div>
        <x-label for="status" value="Status" />
        <x-select id="status" name="status">
            @foreach (['active', 'inactive'] as $option)
                <option value="{{ $option }}" @selected(old('status', $branch->status ?? 'active') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="address" value="Address" />
        <textarea id="address" name="address" rows="3"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('address', $branch->address ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('address')" />
    </div>
</div>
