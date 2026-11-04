<?php

namespace App\Support;

use App\Services\LocaleOptionsService;

/**
 * The currency SaaS package prices are charged in — the Super Admin's
 * "Default currency" (System Settings). Used wherever a package or
 * subscription price is shown (admin panel, client apps via the API).
 */
class PlatformCurrency
{
    public function __construct(private readonly LocaleOptionsService $locale) {}

    public function code(): string
    {
        return $this->locale->defaultCurrency();
    }

    public function symbol(): string
    {
        return $this->locale->currencyCatalog()[$this->code()]['symbol'] ?? $this->code();
    }

    /** e.g. "Rs 2,500.00", "$ 19.99", "د.إ 99.00" */
    public function format(float|int|string|null $amount): string
    {
        return $this->symbol().' '.number_format((float) $amount, 2);
    }
}
