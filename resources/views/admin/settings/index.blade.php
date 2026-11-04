<x-layouts.admin :breadcrumbs="[['label' => 'Configuration', 'url' => route('admin.business-types.index')], ['label' => 'Settings']]">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-neutral-900 dark:text-white">System Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Platform-wide defaults — applied everywhere a client/business hasn't set its own override.
        </p>
    </div>

    @php
        $branding = app(\App\Support\Branding::class);
        $brandingKeys = ['app_name', 'office_address', 'support_email', 'support_phone', 'support_whatsapp', 'support_live_chat_url', 'support_hours', 'company_website'];
    @endphp

    <div class="space-y-6">
        {{-- Company branding: shown in the admin sidebar, browser tab,
             login pages and on the dashboard. --}}
        <x-card class="max-w-4xl">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Company Branding</h2>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Your company name, logo, favicon and office address — shown on the dashboard, the sidebar, the browser tab and the login page.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <div class="sm:col-span-2">
                    <x-label for="app_name" value="Company name" />
                    <x-input id="app_name" name="app_name" :value="old('app_name', $values['app_name'] ?? '')" maxlength="255" />
                    <x-input-error :messages="$errors->get('app_name')" />
                </div>

                {{-- Logo --}}
                <div>
                    <x-label for="app_logo" value="Company logo" />
                    <div class="mt-1 flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-white dark:border-neutral-800">
                            @if ($branding->logoUrl())
                                <img src="{{ $branding->logoUrl() }}" alt="" class="h-full w-full object-contain p-1" data-preview="app_logo">
                            @else
                                <span class="text-lg font-bold text-neutral-400" data-preview-fallback="app_logo">{{ $branding->initials() }}</span>
                                <img src="" alt="" class="hidden h-full w-full object-contain p-1" data-preview="app_logo">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <input id="app_logo" name="app_logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-input="app_logo" class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-neutral-700 hover:file:bg-neutral-200 dark:text-neutral-400 dark:file:bg-neutral-800 dark:file:text-neutral-200">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">PNG, JPG, WEBP or GIF, max 2MB.</p>
                            @if ($branding->logoUrl())
                                <label class="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                                    <input type="checkbox" name="remove_app_logo" value="1" class="rounded border-neutral-300 dark:border-neutral-700"> Remove logo
                                </label>
                            @endif
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('app_logo')" />
                </div>

                {{-- Favicon --}}
                <div>
                    <x-label for="app_favicon" value="Favicon (browser tab icon)" />
                    <div class="mt-1 flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-white dark:border-neutral-800">
                            @if ($branding->faviconUrl())
                                <img src="{{ $branding->faviconUrl() }}" alt="" class="h-8 w-8 object-contain" data-preview="app_favicon">
                            @else
                                <span class="text-xs text-neutral-400" data-preview-fallback="app_favicon">None</span>
                                <img src="" alt="" class="hidden h-8 w-8 object-contain" data-preview="app_favicon">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <input id="app_favicon" name="app_favicon" type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp,image/gif,.ico" data-preview-input="app_favicon" class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-neutral-700 hover:file:bg-neutral-200 dark:text-neutral-400 dark:file:bg-neutral-800 dark:file:text-neutral-200">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Square PNG or ICO (e.g. 32×32 or 64×64), max 512KB.</p>
                            @if ($branding->faviconUrl())
                                <label class="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                                    <input type="checkbox" name="remove_app_favicon" value="1" class="rounded border-neutral-300 dark:border-neutral-700"> Remove favicon
                                </label>
                            @endif
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('app_favicon')" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="office_address" value="Office address" />
                    <textarea id="office_address" name="office_address" rows="3" maxlength="2000" placeholder="e.g. Office #12, 3rd Floor, Blue Area, Islamabad, Pakistan"
                        class="block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:focus:border-white dark:focus:ring-white">{{ old('office_address', $values['office_address'] ?? '') }}</textarea>
                    <x-input-error :messages="$errors->get('office_address')" />
                </div>

                <div>
                    <x-label for="support_email" value="Support email" />
                    <x-input id="support_email" name="support_email" type="email" :value="old('support_email', $values['support_email'] ?? '')" />
                    <x-input-error :messages="$errors->get('support_email')" />
                </div>

                <div>
                    <x-label for="support_phone" value="Support phone" />
                    <x-input id="support_phone" name="support_phone" :value="old('support_phone', $values['support_phone'] ?? '')" />
                    <x-input-error :messages="$errors->get('support_phone')" />
                </div>

                <div>
                    <x-label for="support_whatsapp" value="WhatsApp number" />
                    <x-input id="support_whatsapp" name="support_whatsapp" :value="old('support_whatsapp', $values['support_whatsapp'] ?? '')" placeholder="e.g. +92 300 1234567" />
                    <x-input-error :messages="$errors->get('support_whatsapp')" />
                </div>

                <div>
                    <x-label for="support_hours" value="Support hours" />
                    <x-input id="support_hours" name="support_hours" :value="old('support_hours', $values['support_hours'] ?? '')" placeholder="e.g. Mon-Sat, 10:00 AM - 7:00 PM" />
                    <x-input-error :messages="$errors->get('support_hours')" />
                </div>

                <div>
                    <x-label for="support_live_chat_url" value="Live chat link" />
                    <x-input id="support_live_chat_url" name="support_live_chat_url" type="url" :value="old('support_live_chat_url', $values['support_live_chat_url'] ?? '')" placeholder="https://... (Tawk.to, Crisp, WhatsApp link...)" />
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Leave empty until ready - the Support page shows live chat as "coming soon".</p>
                    <x-input-error :messages="$errors->get('support_live_chat_url')" />
                </div>

                <div>
                    <x-label for="company_website" value="Website" />
                    <x-input id="company_website" name="company_website" type="url" :value="old('company_website', $values['company_website'] ?? '')" placeholder="https://yourcompany.com" />
                    <x-input-error :messages="$errors->get('company_website')" />
                </div>

                <div class="sm:col-span-2">
                    <x-button type="submit">Save branding</x-button>
                </div>
            </form>
        </x-card>

        <x-card class="max-w-2xl">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-white">General</h2>
            </div>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                @foreach ($systemKeys as $key => $definition)
                    @continue(in_array($key, $brandingKeys, true))
                    <div>
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
        // Live preview of a newly picked logo/favicon before saving.
        document.querySelectorAll('[data-preview-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var key = input.dataset.previewInput;
                var file = input.files && input.files[0];
                var img = document.querySelector('[data-preview="' + key + '"]');
                var fallback = document.querySelector('[data-preview-fallback="' + key + '"]');
                if (!file || !img) return;
                img.src = URL.createObjectURL(file);
                img.classList.remove('hidden');
                if (fallback) fallback.classList.add('hidden');
            });
        });

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
