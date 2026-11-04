<?php

/*
|--------------------------------------------------------------------------
| Currency Catalog
|--------------------------------------------------------------------------
|
| Every currency the platform can offer, keyed by ISO 4217 code. The Super
| Admin picks which of these are actually offered to clients via the
| `supported_currencies` system setting (see LocaleOptionsService); a
| business may only choose from that enabled subset.
|
*/

return [
    'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
    'EUR' => ['name' => 'Euro', 'symbol' => '€'],
    'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
    'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'Rs'],
    'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
    'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
    'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs'],
    'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => 'Rs'],
    'AFN' => ['name' => 'Afghan Afghani', 'symbol' => '؋'],
    'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
    'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
    'QAR' => ['name' => 'Qatari Riyal', 'symbol' => '﷼'],
    'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
    'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب'],
    'OMR' => ['name' => 'Omani Rial', 'symbol' => '﷼'],
    'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'د.ا'],
    'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£'],
    'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
    'IRR' => ['name' => 'Iranian Rial', 'symbol' => '﷼'],
    'IQD' => ['name' => 'Iraqi Dinar', 'symbol' => 'ع.د'],
    'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
    'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
    'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
    'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
    'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
    'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
    'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
    'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
    'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
    'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
    'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
    'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
    'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
    'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'MX$'],
    'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
    'ARS' => ['name' => 'Argentine Peso', 'symbol' => '$'],
    'CLP' => ['name' => 'Chilean Peso', 'symbol' => '$'],
    'COP' => ['name' => 'Colombian Peso', 'symbol' => '$'],
    'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
    'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
    'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
    'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
    'PLN' => ['name' => 'Polish Złoty', 'symbol' => 'zł'],
    'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
    'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft'],
    'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
    'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
    'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
    'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
    'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
    'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵'],
    'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'د.م.'],
];
