<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\LocaleOptionsService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    /** Keys rendered/handled by the dedicated currency & timezone section, not the generic loop. */
    private const LOCALE_KEYS = ['default_currency', 'default_timezone', 'supported_currencies', 'supported_timezones'];

    /** Uploaded branding files: setting key => public-disk folder. */
    private const FILE_KEYS = ['app_logo' => 'branding', 'app_favicon' => 'branding'];

    /**
     * Super Admin system-wide settings — Application Name, Logo, Default
     * Currency/Timezone, the currencies/timezones offered to clients,
     * Support Email/Phone, Maintenance Mode, Default Theme — all resolved
     * through SettingsService against the 'system' scope of the registry
     * in config/settings.php.
     */
    public function index(SettingsService $settings, LocaleOptionsService $locale): View
    {
        $this->authorize('viewAny', \App\Models\Setting::class);

        $systemKeys = collect($settings->keysForScope('system'))
            ->except([...self::LOCALE_KEYS, ...array_keys(self::FILE_KEYS)])
            ->all();
        $values = collect($systemKeys)->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key)]);

        $timezoneCatalog = collect($locale->timezoneCatalog())
            ->map(fn (string $tz) => ['id' => $tz, 'label' => '(UTC'.$locale->utcOffset($tz).') '.str_replace('_', ' ', $tz)])
            ->groupBy(fn (array $tz) => str_contains($tz['id'], '/') ? strstr($tz['id'], '/', true) : 'Other');

        return view('admin.settings.index', [
            'systemKeys' => $systemKeys,
            'values' => $values,
            'currencyCatalog' => $locale->currencyCatalog(),
            'timezoneCatalog' => $timezoneCatalog,
            'enabledCurrencies' => $locale->enabledCurrencyCodes(),
            'enabledTimezones' => $locale->enabledTimezoneIds(),
            'defaultCurrency' => $locale->defaultCurrency(),
            'defaultTimezone' => $locale->defaultTimezone(),
        ]);
    }

    public function update(Request $request, SettingsService $settings, LocaleOptionsService $locale, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', \App\Models\Setting::class);

        $request->validate([
            'app_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'office_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Raster images only (no SVG — avoids SVG-borne XSS), same caps
            // as business logos.
            'app_logo' => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'app_favicon' => ['sometimes', 'nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp,gif', 'max:512'],
        ]);

        $this->storeBrandingFiles($request, $settings);

        $currencyCodes = array_keys($locale->currencyCatalog());
        $timezoneIds = $locale->timezoneCatalog();

        // The locale section is only validated when it was actually posted
        // (its hidden marker field) — keeps partial updates of the generic
        // settings working exactly as before.
        if ($request->boolean('_locale_section')) {
            $validated = $request->validate([
                'supported_currencies' => ['required', 'array', 'min:1'],
                'supported_currencies.*' => ['string', Rule::in($currencyCodes)],
                'supported_timezones' => ['required', 'array', 'min:1'],
                'supported_timezones.*' => ['string', Rule::in($timezoneIds)],
                'default_currency' => ['required', 'string', Rule::in($currencyCodes)],
                'default_timezone' => ['required', 'string', Rule::in($timezoneIds)],
            ], [
                'supported_currencies.required' => 'Enable at least one currency.',
                'supported_timezones.required' => 'Enable at least one timezone.',
            ]);

            // The default must always be one of the offered options.
            $currencies = array_values(array_unique([...$validated['supported_currencies'], $validated['default_currency']]));
            $timezones = array_values(array_unique([...$validated['supported_timezones'], $validated['default_timezone']]));

            $settings->set('supported_currencies', json_encode($currencies));
            $settings->set('supported_timezones', json_encode($timezones));
            $settings->set('default_currency', $validated['default_currency']);
            $settings->set('default_timezone', $validated['default_timezone']);
        } else {
            foreach (['default_currency' => $currencyCodes, 'default_timezone' => $timezoneIds] as $key => $allowed) {
                if ($request->has($key)) {
                    $request->validate([$key => ['required', 'string', Rule::in($allowed)]]);
                    $settings->set($key, $request->input($key));
                }
            }
        }

        foreach (array_keys($settings->keysForScope('system')) as $key) {
            if (in_array($key, self::LOCALE_KEYS, true) || array_key_exists($key, self::FILE_KEYS)) {
                continue;
            }
            if ($request->has($key)) {
                $settings->set($key, $request->input($key));
            }
        }

        $logger->log('settings.updated', null, 'System settings updated');

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated.');
    }

    /**
     * Saves a newly uploaded logo/favicon (replacing and deleting the old
     * file), or removes it when its "remove" box was ticked.
     */
    private function storeBrandingFiles(Request $request, SettingsService $settings): void
    {
        foreach (self::FILE_KEYS as $key => $folder) {
            $current = $settings->resolve($key);
            $isStoredFile = is_string($current) && $current !== '' && ! preg_match('#^https?://#i', $current);

            if ($request->hasFile($key)) {
                if ($isStoredFile) {
                    Storage::disk('public')->delete($current);
                }
                $settings->set($key, $request->file($key)->store($folder, 'public'));
            } elseif ($request->boolean("remove_{$key}")) {
                if ($isStoredFile) {
                    Storage::disk('public')->delete($current);
                }
                $settings->set($key, null);
            }
        }
    }
}
