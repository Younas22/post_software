<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Settings']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">System Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Platform-wide defaults — applied everywhere a client/business hasn't set its own override.
        </p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            @csrf
            @method('PUT')

            @foreach ($systemKeys as $key => $definition)
                <div @class(['sm:col-span-2' => in_array($key, ['app_name', 'app_logo'])])>
                    <x-label :for="$key" :value="\Illuminate\Support\Str::of($key)->replace('_', ' ')->title()" />

                    @if ($definition['type'] === 'boolean')
                        <x-select :id="$key" :name="$key">
                            <option value="1" @selected($values[$key])>Yes</option>
                            <option value="0" @selected(! $values[$key])>No</option>
                        </x-select>
                    @elseif ($key === 'default_theme')
                        <x-select :id="$key" :name="$key">
                            <option value="light" @selected($values[$key] === 'light')>Light</option>
                            <option value="dark" @selected($values[$key] === 'dark')>Dark</option>
                        </x-select>
                    @else
                        <x-input :id="$key" :name="$key" :value="$values[$key]" />
                    @endif
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <x-button type="submit">Save settings</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.admin>
