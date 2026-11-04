<?php

/*
|--------------------------------------------------------------------------
| Settings Registry
|--------------------------------------------------------------------------
|
| The single catalog of settings the platform knows about — their type,
| default value, and which scopes (system/client/business/branch) they're
| meaningful at. This is metadata only; actual values still live in the
| generic `settings` key/value table (see Setting model / SettingsService),
| so adding a new setting here never requires a migration.
|
| Structural, always-present business attributes (currency, timezone) are
| real columns on `businesses` — not duplicated here — because they're
| required on every business, not optional configuration. This registry is
| for genuinely optional, open-ended configuration.
|
*/

return [

    'app_name' => ['type' => 'string', 'default' => 'Universal POS SaaS', 'scopes' => ['system']],
    'app_logo' => ['type' => 'string', 'default' => null, 'scopes' => ['system']],
    'default_currency' => ['type' => 'string', 'default' => 'USD', 'scopes' => ['system']],
    'default_timezone' => ['type' => 'string', 'default' => 'UTC', 'scopes' => ['system']],
    // Which currencies/timezones clients may pick for their business — a
    // JSON array of codes, managed on the Super Admin settings page (see
    // LocaleOptionsService). The defaults above are always kept enabled.
    'supported_currencies' => ['type' => 'json', 'default' => ['USD', 'EUR', 'GBP', 'PKR', 'INR', 'AED', 'SAR'], 'scopes' => ['system']],
    'supported_timezones' => ['type' => 'json', 'default' => ['UTC', 'Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kolkata', 'Europe/London', 'America/New_York'], 'scopes' => ['system']],
    'support_email' => ['type' => 'string', 'default' => null, 'scopes' => ['system']],
    'support_phone' => ['type' => 'string', 'default' => null, 'scopes' => ['system']],
    'maintenance_mode' => ['type' => 'boolean', 'default' => false, 'scopes' => ['system']],
    'default_theme' => ['type' => 'string', 'default' => 'light', 'scopes' => ['system', 'client']],

    'tax_rate' => ['type' => 'decimal', 'default' => 0, 'scopes' => ['business', 'branch']],
    'invoice_prefix' => ['type' => 'string', 'default' => 'INV-', 'scopes' => ['business']],
    'receipt_footer' => ['type' => 'string', 'default' => null, 'scopes' => ['business', 'branch']],
    'language' => ['type' => 'string', 'default' => 'en', 'scopes' => ['client', 'business']],
    'date_format' => ['type' => 'string', 'default' => 'Y-m-d', 'scopes' => ['client', 'business']],

];
