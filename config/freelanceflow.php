<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'FreelanceFlow'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        // Invoice number prefix: INV-2026-001
        'prefix' => env('INVOICE_PREFIX', 'INV'),

        // Default payment terms in days
        'default_due_days' => (int) env('INVOICE_DEFAULT_DUE_DAYS', 30),

        // Default GST/tax rate percentage
        'default_tax_rate' => (float) env('INVOICE_DEFAULT_TAX_RATE', 18.0),

        // Maximum file size for invoice PDF in KB
        'pdf_max_size_kb' => (int) env('INVOICE_PDF_MAX_SIZE_KB', 5120),

        // Currency code
        'currency' => env('INVOICE_CURRENCY', 'INR'),

        // Currency symbol
        'currency_symbol' => env('INVOICE_CURRENCY_SYMBOL', '₹'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        // Maximum file size for project attachments in KB (default 10 MB)
        'max_size_kb' => (int) env('UPLOAD_MAX_SIZE_KB', 10240),

        // Allowed MIME types for project attachments
        'allowed_mimes' => explode(',', env(
            'UPLOAD_ALLOWED_MIMES',
            'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip'
        )),

        // Storage disk for attachments (local or s3)
        'disk' => env('UPLOAD_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'api' => [
            'authenticated_per_minute' => (int) env('API_RATE_LIMIT_AUTHENTICATED', 60),
            'guest_per_minute' => (int) env('API_RATE_LIMIT_GUEST', 30),
        ],

        'api_reads' => [
            'authenticated_per_minute' => (int) env('API_READ_RATE_LIMIT_AUTHENTICATED', 120),
            'guest_per_minute' => (int) env('API_READ_RATE_LIMIT_GUEST', 30),
        ],

        'token_creation' => [
            'per_minute' => (int) env('TOKEN_CREATION_RATE_LIMIT', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    */

    'encryption' => [
        'enabled' => (bool) env('ENCRYPTION_ENABLED', true),

        'encrypted_fields' => [
            'clients' => ['notes'],
            'invoices' => ['notes'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Settings
    |--------------------------------------------------------------------------
    */

    'workspace' => [
        // Maximum clients per workspace on free plan
        'free_client_limit' => (int) env('WORKSPACE_FREE_CLIENT_LIMIT', 10),

        // Maximum projects per workspace on free plan
        'free_project_limit' => (int) env('WORKSPACE_FREE_PROJECT_LIMIT', 25),

        // Maximum team members on free plan
        'free_member_limit' => (int) env('WORKSPACE_FREE_MEMBER_LIMIT', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        // Cache TTL in seconds for dashboard stats
        'cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 300),

        // Default revenue chart period in months
        'default_chart_months' => (int) env('DASHBOARD_DEFAULT_CHART_MONTHS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Settings
    |--------------------------------------------------------------------------
    */

    'support' => [
        'email' => env('SUPPORT_EMAIL', 'support@freelanceflow.test'),
        'url' => env('SUPPORT_URL', 'https://freelanceflow.test/support'),
    ],

];
