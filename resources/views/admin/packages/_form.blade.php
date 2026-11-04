@php
    $package ??= null;
    $assignedFeatureIds ??= [];
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="name" value="Name" />
        <x-input id="name" name="name" :value="old('name', $package->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="slug" value="Slug" />
        <x-input id="slug" name="slug" :value="old('slug', $package->slug ?? '')" placeholder="Auto-generated from name if left blank" />
        <x-input-error :messages="$errors->get('slug')" />
    </div>

    <div>
        <x-label for="price" :value="'Price ('.app(\App\Support\PlatformCurrency::class)->code().')'" />
        <x-input id="price" type="number" step="0.01" min="0" name="price" :value="old('price', $package->price ?? '0.00')" required />
        <x-input-error :messages="$errors->get('price')" />
    </div>

    <div>
        <x-label for="billing_cycle" value="Billing Cycle" />
        <x-select id="billing_cycle" name="billing_cycle">
            @foreach (['monthly', 'yearly'] as $option)
                <option value="{{ $option }}" @selected(old('billing_cycle', $package->billing_cycle ?? 'monthly') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('billing_cycle')" />
    </div>

    <div>
        <x-label for="max_businesses" value="Max Businesses" />
        <x-input id="max_businesses" type="number" min="1" name="max_businesses" :value="old('max_businesses', $package->max_businesses ?? '')" placeholder="Unlimited" />
        <x-input-error :messages="$errors->get('max_businesses')" />
    </div>

    <div>
        <x-label for="max_branches" value="Max Branches" />
        <x-input id="max_branches" type="number" min="1" name="max_branches" :value="old('max_branches', $package->max_branches ?? '')" placeholder="Unlimited" />
        <x-input-error :messages="$errors->get('max_branches')" />
    </div>

    <div>
        <x-label for="max_users" value="Max Users" />
        <x-input id="max_users" type="number" min="1" name="max_users" :value="old('max_users', $package->max_users ?? '')" placeholder="Unlimited" />
        <x-input-error :messages="$errors->get('max_users')" />
    </div>

    <div>
        <x-label for="max_devices" value="Max Devices" />
        <x-input id="max_devices" type="number" min="1" name="max_devices" :value="old('max_devices', $package->max_devices ?? '')" placeholder="Unlimited" />
        <x-input-error :messages="$errors->get('max_devices')" />
    </div>

    <div>
        <x-label for="status" value="Status" />
        <x-select id="status" name="status">
            @foreach (['active', 'inactive'] as $option)
                <option value="{{ $option }}" @selected(old('status', $package->status ?? 'active') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('description', $package->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="sm:col-span-2">
        <x-label value="Features" />
        <div class="grid grid-cols-1 gap-x-6 gap-y-2 rounded-md border border-neutral-200 p-4 sm:grid-cols-2 dark:border-neutral-800">
            @forelse ($features as $feature)
                <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                    <input
                        type="checkbox"
                        name="features[]"
                        value="{{ $feature->id }}"
                        @checked(in_array($feature->id, old('features', $assignedFeatureIds)))
                        class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-white"
                    >
                    {{ $feature->name }}
                </label>
            @empty
                <p class="text-sm text-neutral-400 dark:text-neutral-600">No active features to assign yet.</p>
            @endforelse
        </div>
        <x-input-error :messages="$errors->get('features')" />
    </div>
</div>
