@php $client ??= null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="name" value="Name" />
        <x-input id="name" name="name" :value="old('name', $client->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" type="email" name="email" :value="old('email', $client->email ?? '')" required />
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" name="phone" :value="old('phone', $client->phone ?? '')" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>

    <div>
        <x-label for="status" value="Status" />
        <x-select id="status" name="status">
            @foreach (['active', 'suspended', 'cancelled'] as $option)
                <option value="{{ $option }}" @selected(old('status', $client->status ?? 'active') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="4"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('notes', $client->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" />
    </div>
</div>

@unless ($client)
    {{-- Only on create — editing an existing client must never re-create
         its owner login or spawn a second subscription. --}}
    <div class="border-t border-neutral-100 pt-6 dark:border-neutral-800">
        <h2 class="mb-1 text-sm font-semibold text-neutral-900 dark:text-white">Owner login</h2>
        <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
            A password is generated automatically and shown once after creation — copy it to send to the client.
        </p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-label for="owner_name" value="Owner name" />
                <x-input id="owner_name" name="owner_name" :value="old('owner_name')" required />
                <x-input-error :messages="$errors->get('owner_name')" />
            </div>

            <div>
                <x-label for="owner_email" value="Owner email" />
                <x-input id="owner_email" type="email" name="owner_email" :value="old('owner_email')" required />
                <x-input-error :messages="$errors->get('owner_email')" />
            </div>

            <div class="sm:col-span-2">
                <x-label for="package_id" value="Package (optional)" />
                <x-select id="package_id" name="package_id">
                    <option value="">No package yet — assign later from the client's page</option>
                    @foreach ($packages ?? [] as $package)
                        <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>{{ $package->name }} — {{ app(\App\Support\PlatformCurrency::class)->format($package->price) }}/{{ $package->billing_cycle }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('package_id')" />
                <p class="mt-1.5 text-xs text-neutral-400 dark:text-neutral-600">
                    Without a package, the owner can log in but the client dashboard stays locked until a subscription is added (it requires an active subscription).
                </p>
            </div>
        </div>
    </div>
@endunless
