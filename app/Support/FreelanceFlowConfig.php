<?php

// app/Support/FreelanceFlowConfig.php

namespace App\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

class FreelanceFlowConfig
{
    public static function invoicePrefix(): string
    {
        return config('freelanceflow.invoice.prefix', 'INV');
    }

    public static function defaultDueDays(): int
    {
        return (int) config('freelanceflow.invoice.default_due_days', 30);
    }

    public static function defaultTaxRate(): float
    {
        return (float) config('freelanceflow.invoice.default_tax_rate', 18.0);
    }

    public static function currencySymbol(): string
    {
        return config('freelanceflow.invoice.currency_symbol', 'INR');
    }

    public static function currencyCode(): string
    {
        return config('freelanceflow.invoice.currency', 'INR');
    }

    public static function formatCurrency(float|int $amount, ?string $locale = null, ?string $currency = null): string
    {
        $locale ??= app()->getLocale();
        $currency ??= self::currencyCode();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return sprintf('%s %s', $currency, number_format((float) $amount, 2));
    }

    public static function formatDate(CarbonInterface $date, ?string $locale = null): string
    {
        return $date->locale($locale ?? app()->getLocale())->isoFormat('LL');
    }

    public static function formatDateShort(CarbonInterface $date, ?string $locale = null): string
    {
        return $date->locale($locale ?? app()->getLocale())->isoFormat('L');
    }

    public static function supportedLocales(): array
    {
        return config('freelanceflow.locales.supported', ['en']);
    }

    public static function localeName(string $locale): string
    {
        return config("freelanceflow.locales.names.{$locale}", $locale);
    }

    public static function uploadMaxSizeKb(): int
    {
        return (int) config('freelanceflow.uploads.max_size_kb', 10240);
    }

    public static function allowedMimes(): array
    {
        return config('freelanceflow.uploads.allowed_mimes', []);
    }

    public static function uploadDisk(): string
    {
        return config('freelanceflow.uploads.disk', 'local');
    }

    public static function dashboardCacheTtl(): int
    {
        return (int) config('freelanceflow.dashboard.cache_ttl', 300);
    }

    public static function freeClientLimit(): int
    {
        return (int) config('freelanceflow.workspace.free_client_limit', 10);
    }
}
