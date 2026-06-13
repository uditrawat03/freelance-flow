# Day 46 - Two-Factor Authentication

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3:** Advanced Security
> **Read time:** 16 min | **Level:** Intermediate

Passwords are necessary, but they are not enough. A password can be phished, reused, leaked, or guessed. Two-factor authentication adds a second proof: a short-lived code from the user's authenticator app, plus recovery codes for account access when the app is unavailable.

Today we add a complete and scalable two-factor authentication flow to FreelanceFlow:

- encrypted TOTP secrets
- encrypted single-use recovery codes
- setup confirmation before 2FA becomes active
- login challenge screen
- route middleware that blocks protected pages until 2FA is verified
- account settings UI for enabling, disabling, and regenerating codes
- feature tests for the critical paths

---

## Files Changed

### New files

- `config/two_factor.php`
- `database/migrations/2026_06_13_000100_add_two_factor_columns_to_users_table.php`
- `app/Http/Middleware/EnsureTwoFactorAuthenticated.php`
- `app/Livewire/Auth/TwoFactorChallenge.php`
- `app/Livewire/Settings/Index.php`
- `app/Livewire/Settings/TwoFactor.php`
- `resources/views/livewire/auth/two-factor-challenge.blade.php`
- `resources/views/livewire/settings/index.blade.php`
- `resources/views/livewire/settings/two-factor.blade.php`
- `lang/en/auth.php`
- `tests/Feature/TwoFactorAuthenticationTest.php`

### Modified files

- `app/Models/User.php`
- `app/Livewire/Auth/Login.php`
- `routes/web.php`
- `resources/views/partials/sidebar.blade.php`
- `composer.json`
- `composer.lock`

---

## Step 1 - Install TOTP and QR Code Packages

```bash
composer require pragmarx/google2fa bacon/bacon-qr-code
```

We use:

- `pragmarx/google2fa` for RFC 6238 TOTP generation and verification
- `bacon/bacon-qr-code` for rendering an inline SVG QR code

The implementation does not depend on package-specific middleware. FreelanceFlow keeps the authentication flow in application code, which makes the feature easier to test, extend, and reason about.

---

## Step 2 - Add Scalable Configuration

`config/two_factor.php`

```php
<?php

return [
    'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'FreelanceFlow')),
    'window' => (int) env('TWO_FACTOR_WINDOW', 1),
    'recovery_code_count' => (int) env('TWO_FACTOR_RECOVERY_CODE_COUNT', 8),
];
```

Why this matters:

- the issuer can be changed per environment or product brand
- the TOTP tolerance window is configurable
- recovery code count can change without editing model code

For production, keep the window small. `1` allows the previous, current, and next 30-second code, which gives users clock-skew tolerance without being too permissive.

---

## Step 3 - Add 2FA Columns

`database/migrations/2026_06_13_000100_add_two_factor_columns_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->text('two_factor_secret')->nullable()->after('password');
    $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
    $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
});
```

Design notes:

- `two_factor_secret` stores the encrypted TOTP shared secret
- `two_factor_recovery_codes` stores an encrypted JSON array
- `two_factor_confirmed_at` stays `null` until the user proves the authenticator app is set up correctly

That last field prevents lockouts. A user can start setup, fail to scan the QR code, and still log in normally until setup is confirmed.

---

## Step 4 - Add 2FA Behavior to the User Model

`app/Models/User.php` now owns the account-level 2FA behavior:

- `hasTwoFactorEnabled()`
- `hasTwoFactorPending()`
- `enableTwoFactor()`
- `confirmTwoFactor()`
- `disableTwoFactor()`
- `decryptedTwoFactorSecret()`
- `decryptedRecoveryCodes()`
- `generateRecoveryCodes()`
- `regenerateRecoveryCodes()`
- `verifyTwoFactorCode()`
- `useRecoveryCode()`
- `twoFactorQrCodeSvg()`

Key implementation choices:

- secrets and recovery codes are encrypted with Laravel's `Crypt`
- 2FA columns are hidden from serialization
- recovery codes are normalized and compared with `hash_equals`
- used recovery codes are removed immediately
- QR code generation lives close to the user data it represents

Example:

```php
public function hasTwoFactorEnabled(): bool
{
    return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
}
```

The check requires both a secret and confirmation timestamp. A generated secret alone is only a pending setup.

---

## Step 5 - Enforce 2FA with Middleware

`app/Http/Middleware/EnsureTwoFactorAuthenticated.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if (! $user?->hasTwoFactorEnabled() || $request->session()->get('two_factor_verified') === true) {
        return $next($request);
    }

    if ($request->routeIs('two-factor.challenge', 'logout')) {
        return $next($request);
    }

    $request->session()->put('two_factor_intended', $request->fullUrl());

    return redirect()->route('two-factor.challenge');
}
```

This middleware is intentionally simple:

- users without 2FA continue normally
- users already verified in the current session continue normally
- the challenge and logout routes stay reachable
- the original destination is saved and restored after verification

This scales well because every protected web route can share the same middleware instead of duplicating 2FA checks in controllers or Livewire components.

---

## Step 6 - Redirect 2FA Users After Password Login

`app/Livewire/Auth/Login.php`

After `Auth::attempt()` succeeds, the login component checks whether the user has confirmed 2FA:

```php
if (Auth::user()->hasTwoFactorEnabled()) {
    session([
        'two_factor_intended' => route('dashboard'),
        'two_factor_verified' => false,
    ]);

    $this->redirect(route('two-factor.challenge'), navigate: true);

    return;
}
```

The password is still verified first. 2FA is the second step, not a replacement for normal authentication.

---

## Step 7 - Build the Challenge Screen

`app/Livewire/Auth/TwoFactorChallenge.php` supports two verification modes:

- authenticator app code
- recovery code

On success, it stores:

```php
session(['two_factor_verified' => true]);
```

Then it redirects to `two_factor_intended`, falling back to the dashboard.

The Blade view uses the existing auth layout and Flux components, so it visually matches the login and register screens.

---

## Step 8 - Build the Settings Screen

The settings area is split into two components:

- `App\Livewire\Settings\Index`
- `App\Livewire\Settings\TwoFactor`

The 2FA component has three states:

- `idle`: 2FA is off
- `confirming_enable`: secret exists but setup is not confirmed
- `enabled`: 2FA is active

Important flows:

- enabling generates an encrypted secret and encrypted recovery codes
- confirming requires a valid current TOTP code
- disabling requires the current password
- regenerating recovery codes invalidates the old set

This keeps the page feature-complete without mixing settings-page layout code with security logic.

---

## Step 9 - Register Routes

`routes/web.php`

```php
Route::get('/two-factor-challenge', TwoFactorChallenge::class)
    ->middleware('auth')
    ->name('two-factor.challenge');

Route::middleware(['auth', EnsureTwoFactorAuthenticated::class])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/settings', SettingsIndex::class)->name('settings.index');

    // Other authenticated routes...
});
```

The challenge route is authenticated, but it is not inside the 2FA-protected group. That avoids a redirect loop.

---

## Step 10 - Add Navigation

The sidebar now links to:

```php
route('settings.index')
```

The link is present in both desktop and mobile navigation and uses the existing active-route styling.

---

## Step 11 - Add Tests

`tests/Feature/TwoFactorAuthenticationTest.php` covers:

- enabling, confirming, and disabling 2FA
- encrypted storage of the TOTP secret
- single-use recovery codes
- login redirect to the 2FA challenge
- middleware protection before verification
- challenge verification with recovery code
- pending setup not forcing the challenge

Run the focused suite:

```bash
php artisan test tests/Feature/TwoFactorAuthenticationTest.php
```

Run the full suite:

```bash
php artisan test
```

---

## Security Checklist

- TOTP secret is encrypted at rest
- recovery codes are encrypted at rest
- recovery codes are single-use
- 2FA is inactive until a code is confirmed
- route protection is centralized in middleware
- logout remains available during the challenge
- password confirmation is required before disabling 2FA
- 2FA session state is reset on login and removed when disabling

---

## Scalability Notes

This version is intentionally compact, but it leaves room for production growth:

- move 2FA methods into a dedicated domain service if more factors are added
- store hashed recovery codes instead of encrypted plaintext if you do not need to show codes after creation
- add rate limiting to the challenge form for brute-force protection
- add audit events for enable, disable, recovery-code use, and regeneration
- require password confirmation before beginning setup in high-risk environments
- notify users by email when 2FA is enabled or disabled
- consider WebAuthn/passkeys as a stronger second factor later

---

## What We Learned Today

- TOTP creates short-lived codes using a shared secret and time window
- 2FA setup should have a pending state to prevent accidental lockout
- recovery codes must be single-use
- security-sensitive fields should be hidden from serialization
- middleware is the right layer for enforcing session-level 2FA completion
- tests should cover both the happy path and the lockout-prevention edge case

---

## Day 47 Preview - Authorization Hardening

Next we will continue tightening FreelanceFlow by reviewing authorization boundaries, route access, and policy coverage so account security is backed by strong ownership checks throughout the app.
