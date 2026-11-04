@php
    $businessType ??= null;
    $theme = $businessType->theme ?? [];
    $terms = $businessType->terminology ?? [];
    $modules = $businessType->modules ?? [];
    $hidden = old('modules.hidden', $modules['hidden'] ?? []);
    $enabled = old('modules.enabled', $modules['enabled'] ?? []);
    $primary = old('theme.primary_color', $theme['primary_color'] ?? $themeDefaults['primary_color']);
    $sidebarStyle = old('theme.sidebar_style', $theme['sidebar_style'] ?? $themeDefaults['sidebar_style']);
    $highlights = old('theme.login_highlights', implode("\n", $theme['login_highlights'] ?? []));
    $loginImage = ! empty($theme['login_image']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($theme['login_image']) : null;
    $textareaClass = 'block w-full rounded-md border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-white dark:focus:ring-white';
    $sectionTitle = 'text-sm font-semibold text-neutral-900 dark:text-white';
    $sectionHint = 'mt-1 text-xs text-neutral-500 dark:text-neutral-400';
@endphp

<div class="space-y-8">
    {{-- ───────────── Basics ───────────── --}}
    <section>
        <h2 class="{{ $sectionTitle }}">Basics</h2>
        <p class="{{ $sectionHint }}">The slug is also this type's own login URL.</p>
        <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-label for="name" value="Name" />
                <x-input id="name" name="name" :value="old('name', $businessType->name ?? '')" required autofocus />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-label for="slug" value="Slug" />
                <x-input id="slug" name="slug" :value="old('slug', $businessType->slug ?? '')" placeholder="Auto-generated from name if left blank" />
                <x-input-error :messages="$errors->get('slug')" />
                @if ($businessType?->slug)
                    <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Login URL:
                        <a href="{{ $frontendUrl }}/login/{{ $businessType->slug }}" target="_blank" rel="noopener" class="font-mono text-neutral-900 underline dark:text-white">{{ $frontendUrl }}/login/{{ $businessType->slug }}</a>
                    </p>
                @endif
            </div>

            <div class="sm:col-span-2">
                <x-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2" class="{{ $textareaClass }}">{{ old('description', $businessType->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div>
                <x-label for="icon" value="Icon (optional)" />
                <x-input id="icon" name="icon" :value="old('icon', $businessType->icon ?? '')" placeholder="e.g. utensils-crossed, pill, stethoscope" />
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">A <a href="https://lucide.dev/icons" target="_blank" rel="noopener" class="underline">Lucide icon</a> name — shown on the login page.</p>
                <x-input-error :messages="$errors->get('icon')" />
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $businessType->is_active ?? true))
                        class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-white">
                    Active
                </label>
            </div>
        </div>
    </section>

    {{-- ───────────── Theme & login page ───────────── --}}
    <section class="border-t border-neutral-200 pt-8 dark:border-neutral-800">
        <h2 class="{{ $sectionTitle }}">Theme &amp; login page</h2>
        <p class="{{ $sectionHint }}">Businesses of this type see these colours in their POS and this message on their login page.</p>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:col-span-3">
                <div>
                    <x-label for="primary_color" value="Brand colour" />
                    <div class="flex items-center gap-2">
                        <input type="color" id="primary_color_picker" value="{{ $primary }}" class="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-neutral-300 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-900" aria-label="Pick brand colour">
                        <x-input id="primary_color" name="theme[primary_color]" :value="$primary" placeholder="#00bf63" class="font-mono" />
                    </div>
                    <x-input-error :messages="$errors->get('theme.primary_color')" />
                </div>

                <div>
                    <x-label for="sidebar_style" value="Sidebar style" />
                    <x-select id="sidebar_style" name="theme[sidebar_style]">
                        @foreach ($sidebarStyles as $value => $label)
                            <option value="{{ $value }}" @selected($sidebarStyle === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('theme.sidebar_style')" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="login_headline" value="Login headline" />
                    <x-input id="login_headline" name="theme[login_headline]" :value="old('theme.login_headline', $theme['login_headline'] ?? '')" :placeholder="$themeDefaults['login_headline']" maxlength="120" />
                    <x-input-error :messages="$errors->get('theme.login_headline')" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="login_tagline" value="Login tagline" />
                    <textarea id="login_tagline" name="theme[login_tagline]" rows="2" maxlength="300" class="{{ $textareaClass }}" placeholder="{{ $themeDefaults['login_tagline'] }}">{{ old('theme.login_tagline', $theme['login_tagline'] ?? '') }}</textarea>
                    <x-input-error :messages="$errors->get('theme.login_tagline')" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="login_highlights" value="Login highlights (one per line)" />
                    <textarea id="login_highlights" name="theme[login_highlights]" rows="3" class="{{ $textareaClass }}" placeholder="{{ implode("\n", $themeDefaults['login_highlights']) }}">{{ $highlights }}</textarea>
                    <x-input-error :messages="$errors->get('theme.login_highlights')" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="login_image" value="Login background image" />
                    <input type="file" id="login_image" name="login_image" accept="image/*"
                        class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-700 dark:text-neutral-400 dark:file:bg-white dark:file:text-neutral-900">
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Optional. A wide photo works best (e.g. 1600×1200, max 4 MB). Without one, a colour gradient is used.</p>
                    @if ($loginImage)
                        <label class="mt-2 flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input type="checkbox" name="remove_login_image" value="1" class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800">
                            Remove current image
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('login_image')" />
                </div>
            </div>

            {{-- Live preview of the branded login + sidebar --}}
            <div class="lg:col-span-2">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Preview</p>
                <div class="overflow-hidden rounded-xl border border-neutral-200 shadow-sm dark:border-neutral-800">
                    <div id="bt-preview-hero" class="relative p-5 text-white" style="min-height: 200px; background-color: {{ $primary }}; background-size: cover; background-position: center;">
                        <p id="bt-preview-name" class="text-xs font-semibold uppercase tracking-wide" style="opacity:.85"></p>
                        <p id="bt-preview-headline" class="mt-3 text-lg font-semibold leading-snug"></p>
                        <p id="bt-preview-tagline" class="mt-1 text-xs" style="opacity:.85"></p>
                        <ul id="bt-preview-highlights" class="mt-3 space-y-1 text-xs"></ul>
                    </div>
                    <div class="flex bg-white dark:bg-neutral-900" style="height: 120px">
                        <div id="bt-preview-sidebar" class="space-y-1.5 p-2" style="width: 42%">
                            <div class="rounded-md px-2 py-1.5 text-xs font-medium" id="bt-preview-active" style="color: #fff">Dashboard</div>
                            <div class="bt-preview-link rounded-md px-2 py-1.5 text-xs" data-term="pos">{{ $terms['pos'] ?? $terminologyDefaults['pos'] }}</div>
                            <div class="bt-preview-link rounded-md px-2 py-1.5 text-xs" data-term="customers">{{ $terms['customers'] ?? $terminologyDefaults['customers'] }}</div>
                        </div>
                        <div class="flex-1 space-y-2 p-3">
                            <div class="h-2 w-2/3 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                            <div class="h-2 w-1/2 rounded bg-neutral-200 dark:bg-neutral-700"></div>
                            <div class="mt-3 inline-block rounded-md px-3 py-1.5 text-xs font-medium text-white" id="bt-preview-button">Sign in</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────── Terminology ───────────── --}}
    <section class="border-t border-neutral-200 pt-8 dark:border-neutral-800">
        <h2 class="{{ $sectionTitle }}">Terminology</h2>
        <p class="{{ $sectionHint }}">What this trade calls things. Leave blank to use the default shown — e.g. a dental clinic uses "Patient" / "Patients" and "Treatment" / "Treatments".</p>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($terminologyDefaults as $key => $default)
                <div>
                    <x-label :for="'term_'.$key" :value="$key === 'pos' ? 'POS screen' : ucfirst($key)" />
                    <x-input :id="'term_'.$key" :name="'terminology['.$key.']'" :value="old('terminology.'.$key, $terms[$key] ?? '')" :placeholder="$default" maxlength="40" data-term-input="{{ $key }}" />
                    <x-input-error :messages="$errors->get('terminology.'.$key)" />
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────── Modules ───────────── --}}
    <section class="border-t border-neutral-200 pt-8 dark:border-neutral-800">
        <h2 class="{{ $sectionTitle }}">Modules</h2>
        <p class="{{ $sectionHint }}">Hide screens this trade doesn't need, or switch on extra modules.</p>
        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Hide from sidebar</p>
                <div class="space-y-2">
                    @foreach ($hideableModules as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input type="checkbox" name="modules[hidden][]" value="{{ $key }}" @checked(in_array($key, $hidden, true))
                                class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Extra modules</p>
                <div class="space-y-2">
                    @foreach ($optionalModules as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input type="checkbox" name="modules[enabled][]" value="{{ $key }}" @checked(in_array($key, $enabled, true))
                                class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    (function () {
        const $ = (sel) => document.querySelector(sel);
        const picker = $('#primary_color_picker');
        const colorInput = $('#primary_color');
        const hero = $('#bt-preview-hero');
        const sidebar = $('#bt-preview-sidebar');
        const sidebarSelect = $('#sidebar_style');
        const removeBox = document.querySelector('input[name="remove_login_image"]');
        const existingImage = @json($loginImage);
        const valid = (v) => /^#[0-9a-fA-F]{6}$/.test(v);
        let uploadedImage = null;

        function paint() {
            const color = valid(colorInput.value) ? colorInput.value : '#00bf63';
            const img = uploadedImage || (removeBox && removeBox.checked ? null : existingImage);
            hero.style.backgroundColor = color;
            hero.style.backgroundImage = img
                ? `linear-gradient(160deg, rgba(0,0,0,.25), rgba(0,0,0,.7)), url('${img}')`
                : 'linear-gradient(160deg, rgba(255,255,255,.18), rgba(0,0,0,.35))';
            $('#bt-preview-button').style.background = color;

            const style = sidebarSelect.value;
            const onColor = style === 'dark' || style === 'brand';
            sidebar.style.background = style === 'dark' ? '#111827' : style === 'brand' ? color : '#fafafa';
            sidebar.querySelectorAll('.bt-preview-link').forEach((el) => (el.style.color = onColor ? 'rgba(255,255,255,.8)' : '#404040'));
            $('#bt-preview-active').style.background = style === 'brand' ? 'rgba(255,255,255,.22)' : color;
        }

        function text() {
            $('#bt-preview-name').textContent = $('#name').value || 'Business type';
            $('#bt-preview-headline').textContent = $('#login_headline').value || $('#login_headline').placeholder;
            $('#bt-preview-tagline').textContent = $('#login_tagline').value || $('#login_tagline').placeholder;
            const lines = ($('#login_highlights').value || $('#login_highlights').placeholder).split(/\n/).map((l) => l.trim()).filter(Boolean);
            const list = $('#bt-preview-highlights');
            list.innerHTML = '';
            lines.slice(0, 4).forEach((line) => {
                const li = document.createElement('li');
                li.textContent = '✓  ' + line;
                list.appendChild(li);
            });
            document.querySelectorAll('[data-term]').forEach((el) => {
                const input = document.querySelector(`[data-term-input="${el.dataset.term}"]`);
                if (input) el.textContent = input.value || input.placeholder;
            });
        }

        picker.addEventListener('input', () => { colorInput.value = picker.value; paint(); });
        colorInput.addEventListener('input', () => { if (valid(colorInput.value)) picker.value = colorInput.value; paint(); });
        sidebarSelect.addEventListener('change', paint);
        ['#name', '#login_headline', '#login_tagline', '#login_highlights', '#term_pos', '#term_customers'].forEach((s) => $(s) && $(s).addEventListener('input', text));
        $('#login_image').addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            uploadedImage = file ? URL.createObjectURL(file) : null;
            paint();
        });
        if (removeBox) removeBox.addEventListener('change', paint);

        paint();
        text();
    })();
</script>
