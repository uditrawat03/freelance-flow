# Day 49 - Localization and i18n

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3:** Advanced  
> **Read time:** 15 min | **Level:** Intermediate

FreelanceFlow started with one interface language and one default formatting style. Today we add a scalable localization foundation: each user can store a preferred locale, every web request applies that locale automatically, UI strings move into translation files, and dates/currency flow through centralized formatting helpers.

---

## What Changed

### New files

- `database/migrations/2026_06_14_120000_add_locale_to_users_table.php`
- `app/Http/Middleware/SetUserLocale.php`
- `app/Livewire/Settings/LocaleSwitcher.php`
- `resources/views/livewire/settings/locale-switcher.blade.php`
- `lang/en/app.php`
- `lang/hi/app.php`
- `tests/Feature/LocalizationTest.php`

### Updated files

- `app/Models/User.php`
- `bootstrap/app.php`
- `config/freelanceflow.php`
- `app/Support/FreelanceFlowConfig.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/livewire/settings/index.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `app/Livewire/Clients/Create.php`
- `app/Livewire/Invoices/InvoiceList.php`

---

## Step 1 - Store the User Locale

Add a nullable-safe `locale` preference to each user. The column is indexed because locale can later be useful for support filters, email campaign segmentation, and background notification batching.

```php
// database/migrations/2026_06_14_120000_add_locale_to_users_table.php
Schema::table('users', function (Blueprint $table): void {
    $table->string('locale', 10)->default('en')->after('email')->index();
});
```

The `User` model in this codebase uses PHP attributes for fillable fields, so the locale is added there:

```php
#[Fillable(['name', 'email', 'password', 'locale'])]
class User extends Authenticatable
{
    public function setLocale(string $locale): void
    {
        if (! in_array($locale, config('freelanceflow.locales.supported', ['en']), true)) {
            return;
        }

        $this->forceFill(['locale' => $locale])->save();
    }

    public function getLocale(): string
    {
        $locale = $this->locale ?: config('app.locale', 'en');

        return in_array($locale, config('freelanceflow.locales.supported', ['en']), true)
            ? $locale
            : config('app.locale', 'en');
    }
}
```

The guard inside `setLocale()` keeps unsupported locale codes out of the database even if a caller bypasses Livewire validation.

---

## Step 2 - Configure Supported Locales

Locales are centralized in `config/freelanceflow.php`:

```php
'locales' => [
    'supported' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('APP_SUPPORTED_LOCALES', 'en,hi'))
    ))),

    'names' => [
        'en' => 'English',
        'hi' => 'Hindi',
    ],
],
```

To add another language later:

1. Add the locale code to `APP_SUPPORTED_LOCALES`.
2. Add a display name to `freelanceflow.locales.names`.
3. Create `lang/{locale}/app.php`.

No controller or component rules need to be rewritten.

---

## Step 3 - Apply Locale Per Request

The `SetUserLocale` middleware reads the authenticated user's preference and applies it to both Laravel translations and Carbon date formatting:

```php
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->getLocale() ?? config('app.locale', 'en');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
```

Register it in the web middleware group inside `bootstrap/app.php`:

```php
$middleware->appendToGroup('web', [
    \App\Http\Middleware\SetUserLocale::class,
    \App\Http\Middleware\EnsureWorkspaceSelected::class,
]);
```

The app layout also now renders the active locale:

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

---

## Step 4 - Add Translation Files

Application copy now lives in `lang/en/app.php` and `lang/hi/app.php`.

```php
return [
    'nav' => [
        'dashboard' => 'Dashboard',
        'clients' => 'Clients',
        'projects' => 'Projects',
        'invoices' => 'Invoices',
        'settings' => 'Settings',
    ],

    'settings' => [
        'title' => 'Settings',
        'language' => 'Language',
        'language_saved' => 'Language preference saved.',
    ],
];
```

Blade views now call translation keys instead of hardcoded strings:

```blade
<span>{{ __('app.nav.dashboard') }}</span>
```

Livewire action messages use the same pattern:

```php
session()->flash('success', __('app.invoices.marked_paid', [
    'number' => $invoice->number,
]));
```

---

## Step 5 - Build the Locale Switcher

The settings page includes a Livewire component:

```blade
<livewire:settings.locale-switcher />
```

The component validates against the configured locale list, saves the user's preference, and immediately updates the current request:

```php
$this->validate([
    'locale' => ['required', 'string', Rule::in(FreelanceFlowConfig::supportedLocales())],
]);

auth()->user()->setLocale($this->locale);

app()->setLocale($this->locale);
Carbon::setLocale($this->locale);
```

This gives instant feedback after saving and keeps the next request consistent through middleware.

---

## Step 6 - Centralize Formatting

`FreelanceFlowConfig` now owns locale-aware display helpers:

```php
FreelanceFlowConfig::formatCurrency(50000);
FreelanceFlowConfig::formatDate(now());
FreelanceFlowConfig::formatDateShort(now());
```

Currency formatting uses PHP's `intl` extension when available:

```php
if (class_exists(NumberFormatter::class)) {
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    $formatted = $formatter->formatCurrency($amount, $currency);
}
```

If `intl` is missing, the helper falls back to a stable `INR 50,000.00` style string. That makes local development and CI reliable while still supporting proper locale formatting in production.

---

## Tests Added

`tests/Feature/LocalizationTest.php` covers:

- A user can save a supported locale.
- Unsupported locale codes are rejected.
- Authenticated web requests use the saved locale.
- Formatting helpers return stable output.

Run:

```bash
php artisan test
```

---

## Scalability Notes

- Keep supported locales in config, not duplicated inside components.
- Translate by stable keys such as `app.nav.dashboard`, not by English text.
- Use replacement parameters for dynamic copy: `__('app.invoices.marked_paid', ['number' => $number])`.
- Route all money/date display through helpers so reports, emails, PDFs, and Livewire screens stay consistent.
- Add tests when adding a new locale to catch missing dictionaries or unsupported config.

---

## Day 49 Outcome

FreelanceFlow now has the foundation for multilingual UI, per-user language preferences, locale-aware dates and currency, and test coverage around the localization flow. The app is still small enough to translate incrementally, but the architecture no longer assumes English-only growth.
