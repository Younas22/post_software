@php $business ??= null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-label for="client_id" value="Client" />
        <x-select id="client_id" name="client_id" required>
            <option value="">Select a client…</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) old('client_id', $business->client_id ?? '') === (string) $client->id)>{{ $client->name }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('client_id')" />
    </div>

    <div>
        <x-label for="business_type_id" value="Business Type" />
        <x-select id="business_type_id" name="business_type_id" required>
            <option value="">Select a type…</option>
            @foreach ($businessTypes as $type)
                <option value="{{ $type->id }}" @selected((string) old('business_type_id', $business->business_type_id ?? '') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('business_type_id')" />
    </div>

    <div>
        <x-label for="name" value="Business Name" />
        <x-input id="name" name="name" :value="old('name', $business->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" name="phone" :value="old('phone', $business->phone ?? '')" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>

    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" type="email" name="email" :value="old('email', $business->email ?? '')" />
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <x-label for="status" value="Status" />
        <x-select id="status" name="status">
            @foreach (['active', 'inactive'] as $option)
                <option value="{{ $option }}" @selected(old('status', $business->status ?? 'active') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div>
        <x-label for="currency" value="Currency" />
        <x-input id="currency" name="currency" maxlength="3" class="uppercase" :value="old('currency', $business->currency ?? 'USD')" required />
        <x-input-error :messages="$errors->get('currency')" />
    </div>

    <div>
        <x-label for="timezone" value="Timezone" />
        <x-input id="timezone" name="timezone" :value="old('timezone', $business->timezone ?? 'UTC')" required placeholder="e.g. Asia/Karachi" />
        <x-input-error :messages="$errors->get('timezone')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="address" value="Address" />
        <textarea id="address" name="address" rows="3"
            class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white"
        >{{ old('address', $business->address ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('address')" />
    </div>

    <div class="sm:col-span-2">
        <x-label for="logo" value="Logo" />
        @if (($business->logo ?? null))
            <img src="{{ \Illuminate\Support\Facades\Storage::url($business->logo) }}" alt="" class="mb-2 h-12 w-12 rounded-md border border-neutral-200 object-cover dark:border-neutral-800">
        @endif
        <input id="logo" type="file" name="logo" accept="image/*"
            class="block w-full text-sm text-neutral-600 file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-neutral-700 hover:file:bg-neutral-200 dark:text-neutral-400 dark:file:bg-neutral-800 dark:file:text-neutral-300 dark:hover:file:bg-neutral-700">
        <x-input-error :messages="$errors->get('logo')" />
    </div>
</div>
