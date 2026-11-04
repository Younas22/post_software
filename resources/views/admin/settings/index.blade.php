<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Settings']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">System Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Platform-wide defaults — applied everywhere a client/business hasn't set its own override.
        </p>
    </div>

    <div class="space-y-6">
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

        <x-card class="max-w-4xl">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Currencies &amp; Timezones</h2>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Choose which currencies and timezones clients can pick for their business (web &amp; desktop app). The default is used for new businesses and is always enabled.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8" data-locale-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="_locale_section" value="1">

                @php
                    $selectedCurrencies = old('supported_currencies', $enabledCurrencies);
                    $selectedTimezones = old('supported_timezones', $enabledTimezones);
                @endphp

                {{-- Currencies --}}
                <section data-picker>
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Currencies</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400"><span data-count>{{ count($selectedCurrencies) }}</span> enabled</p>
                        </div>
                        <div class="w-full sm:w-64">
                            <x-label for="default_currency" value="Default currency" />
                            <x-select id="default_currency" name="default_currency" data-default-select>
                                @foreach ($currencyCatalog as $code => $currency)
                                    <option value="{{ $code }}" @selected(old('default_currency', $defaultCurrency) === $code)>{{ $code }} — {{ $currency['name'] }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <x-input type="search" placeholder="Search currencies…" class="sm:max-w-xs" data-search />
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" data-select-all>Select all</button>
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" data-clear-all>Clear</button>
                    </div>

                    <div class="grid max-h-72 grid-cols-1 gap-1 overflow-y-auto rounded-md border border-neutral-200 p-2 sm:grid-cols-2 lg:grid-cols-3 dark:border-neutral-800">
                        @foreach ($currencyCatalog as $code => $currency)
                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800" data-option data-search-text="{{ strtolower($code.' '.$currency['name']) }}">
                                <input type="checkbox" name="supported_currencies[]" value="{{ $code }}" class="rounded border-neutral-300 dark:border-neutral-700" @checked(in_array($code, $selectedCurrencies, true))>
                                <span class="font-medium">{{ $code }}</span>
                                <span class="truncate text-neutral-500 dark:text-neutral-400">{{ $currency['name'] }} ({{ $currency['symbol'] }})</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('supported_currencies')" />
                    <x-input-error :messages="$errors->get('default_currency')" />
                </section>

                {{-- Timezones --}}
                <section data-picker>
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Timezones</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400"><span data-count>{{ count($selectedTimezones) }}</span> enabled</p>
                        </div>
                        <div class="w-full sm:w-64">
                            <x-label for="default_timezone" value="Default timezone" />
                            <x-select id="default_timezone" name="default_timezone" data-default-select>
                                @foreach ($timezoneCatalog as $region => $zones)
                                    <optgroup label="{{ $region }}">
                                        @foreach ($zones as $tz)
                                            <option value="{{ $tz['id'] }}" @selected(old('default_timezone', $defaultTimezone) === $tz['id'])>{{ $tz['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </x-select>
                        </div>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <x-input type="search" placeholder="Search timezones, e.g. Karachi…" class="sm:max-w-xs" data-search />
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" data-select-all>Select visible</button>
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" data-clear-all>Clear visible</button>
                        <label class="ml-auto flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                            <input type="checkbox" class="rounded border-neutral-300 dark:border-neutral-700" data-only-selected>
                            Show enabled only
                        </label>
                    </div>

                    <div class="max-h-96 overflow-y-auto rounded-md border border-neutral-200 p-2 dark:border-neutral-800">
                        @foreach ($timezoneCatalog as $region => $zones)
                            <div class="mb-2" data-group>
                                <p class="sticky top-0 bg-white px-2 py-1 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400">{{ $region }}</p>
                                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($zones as $tz)
                                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800" data-option data-search-text="{{ strtolower($tz['label']) }}">
                                            <input type="checkbox" name="supported_timezones[]" value="{{ $tz['id'] }}" class="rounded border-neutral-300 dark:border-neutral-700" @checked(in_array($tz['id'], $selectedTimezones, true))>
                                            <span class="truncate">{{ $tz['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('supported_timezones')" />
                    <x-input-error :messages="$errors->get('default_timezone')" />
                </section>

                <div>
                    <x-button type="submit">Save currencies &amp; timezones</x-button>
                </div>
            </form>
        </x-card>
    </div>

    <script>
        (function () {
            document.querySelectorAll('[data-locale-form] [data-picker]').forEach(function (picker) {
                var search = picker.querySelector('[data-search]');
                var onlySelected = picker.querySelector('[data-only-selected]');
                var count = picker.querySelector('[data-count]');
                var defaultSelect = picker.querySelector('[data-default-select]');
                var options = Array.prototype.slice.call(picker.querySelectorAll('[data-option]'));
                var groups = Array.prototype.slice.call(picker.querySelectorAll('[data-group]'));

                function box(option) { return option.querySelector('input[type=checkbox]'); }
                function visible(option) { return !option.classList.contains('hidden'); }

                function refresh() {
                    var q = (search.value || '').trim().toLowerCase();
                    var only = onlySelected && onlySelected.checked;
                    options.forEach(function (option) {
                        var match = (!q || option.dataset.searchText.indexOf(q) !== -1) && (!only || box(option).checked);
                        option.classList.toggle('hidden', !match);
                    });
                    groups.forEach(function (group) {
                        var any = group.querySelectorAll('[data-option]:not(.hidden)').length > 0;
                        group.classList.toggle('hidden', !any);
                    });
                    count.textContent = options.filter(function (o) { return box(o).checked; }).length;
                }

                // The default is always offered — keep its checkbox ticked.
                function lockDefault() {
                    options.forEach(function (option) {
                        var input = box(option);
                        if (input.value === defaultSelect.value) input.checked = true;
                    });
                    refresh();
                }

                search.addEventListener('input', refresh);
                if (onlySelected) onlySelected.addEventListener('change', refresh);
                defaultSelect.addEventListener('change', lockDefault);
                picker.addEventListener('change', function (e) {
                    if (e.target.matches('[data-option] input') && !e.target.checked && e.target.value === defaultSelect.value) {
                        e.target.checked = true;
                    }
                    refresh();
                });
                picker.querySelector('[data-select-all]').addEventListener('click', function () {
                    options.filter(visible).forEach(function (o) { box(o).checked = true; });
                    refresh();
                });
                picker.querySelector('[data-clear-all]').addEventListener('click', function () {
                    options.filter(visible).forEach(function (o) {
                        if (box(o).value !== defaultSelect.value) box(o).checked = false;
                    });
                    refresh();
                });

                lockDefault();
            });
        })();
    </script>
</x-layouts.admin>
