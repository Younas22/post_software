<?php

namespace App\Services;

use DateTime;
use DateTimeZone;

/**
 * The currencies and timezones a business may choose from. The Super Admin
 * enables a subset of the full catalogs (config/currencies.php and PHP's
 * own timezone database) via the `supported_currencies` /
 * `supported_timezones` system settings; the system defaults are always
 * kept in that subset so a new business can never be created with a
 * value its own picker can't show.
 */
class LocaleOptionsService
{
    public function __construct(private readonly SettingsService $settings) {}

    /** @return array<string, array{name: string, symbol: string}> every currency the platform knows */
    public function currencyCatalog(): array
    {
        return config('currencies', []);
    }

    /** @return list<string> every valid IANA timezone identifier */
    public function timezoneCatalog(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function defaultCurrency(): string
    {
        return (string) ($this->settings->resolve('default_currency') ?: 'USD');
    }

    public function defaultTimezone(): string
    {
        return (string) ($this->settings->resolve('default_timezone') ?: 'UTC');
    }

    /** @return list<string> enabled currency codes, catalog order */
    public function enabledCurrencyCodes(): array
    {
        $enabled = $this->decodeList($this->settings->resolve('supported_currencies'));
        $enabled[] = $this->defaultCurrency();

        return array_values(array_filter(
            array_keys($this->currencyCatalog()),
            fn (string $code) => in_array($code, $enabled, true)
        ));
    }

    /** @return list<string> enabled timezone identifiers, catalog order */
    public function enabledTimezoneIds(): array
    {
        $enabled = $this->decodeList($this->settings->resolve('supported_timezones'));
        $enabled[] = $this->defaultTimezone();

        return array_values(array_filter(
            $this->timezoneCatalog(),
            fn (string $tz) => in_array($tz, $enabled, true)
        ));
    }

    /**
     * Codes a given business may save: the enabled set, plus whatever it
     * already has — so an admin narrowing the list later never makes an
     * existing business's profile form impossible to re-save unchanged.
     *
     * @return list<string>
     */
    public function allowedCurrencyCodes(?string $current = null): array
    {
        return array_values(array_unique(array_filter([...$this->enabledCurrencyCodes(), $current])));
    }

    /** @return list<string> */
    public function allowedTimezoneIds(?string $current = null): array
    {
        return array_values(array_unique(array_filter([...$this->enabledTimezoneIds(), $current])));
    }

    /**
     * Picker-ready options for client apps and admin forms.
     *
     * @return list<array{code: string, name: string, symbol: string, label: string}>
     */
    public function currencyOptions(?string $current = null): array
    {
        $catalog = $this->currencyCatalog();

        return array_map(function (string $code) use ($catalog) {
            $entry = $catalog[$code] ?? ['name' => $code, 'symbol' => $code];

            return [
                'code' => $code,
                'name' => $entry['name'],
                'symbol' => $entry['symbol'],
                'label' => "{$code} — {$entry['name']} ({$entry['symbol']})",
            ];
        }, $this->allowedCurrencyCodes($current));
    }

    /** @return list<array{id: string, offset: string, label: string}> */
    public function timezoneOptions(?string $current = null): array
    {
        return array_map(fn (string $tz) => [
            'id' => $tz,
            'offset' => $this->utcOffset($tz),
            'label' => '(UTC'.$this->utcOffset($tz).') '.str_replace('_', ' ', $tz),
        ], $this->allowedTimezoneIds($current));
    }

    public function utcOffset(string $timezone): string
    {
        try {
            return (new DateTime('now', new DateTimeZone($timezone)))->format('P');
        } catch (\Exception) {
            return '+00:00';
        }
    }

    /** @return list<string> */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }

        return [];
    }
}
