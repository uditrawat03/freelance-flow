<?php

// app/Support/FreelanceFlowConfig.php
namespace App\Support;

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
        return config('freelanceflow.invoice.currency_symbol', '₹');
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