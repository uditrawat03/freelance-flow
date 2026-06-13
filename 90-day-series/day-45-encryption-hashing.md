# Day 45: Encryption & Hashing

> **Series:** FreelanceFlow - Laravel Zero to Hero  
> **Phase:** Advanced Security  
> **Read time:** 15 min  
> **Level:** Intermediate

FreelanceFlow stores client notes and invoice notes that may contain private business context. If a database backup, read replica, or SQL console is exposed, plain-text notes are immediately readable.

Today we make those fields safer by encrypting them at rest, keeping passwords hashed, redacting sensitive log context, and adding tests that prove the behavior.

---

## What Changed

New files:

- `app/Casts/AsEncryptedString.php` - encrypts model attributes before storage and decrypts them on read.
- `app/ValueObjects/SensitiveString.php` - wraps sensitive text and masks it in string, debug, and JSON output.
- `database/migrations/2026_06_13_000000_encrypt_existing_notes.php` - backfills existing plain-text notes in chunks.
- `tests/Feature/EncryptionHashingTest.php` - covers encryption, hashing, redaction, and masking behavior.

Modified files:

- `app/Models/Client.php` - encrypts `notes`.
- `app/Models/Invoice.php` - encrypts `notes`.
- `app/Services/Logger.php` - recursively redacts sensitive keys from log context.
- `config/freelanceflow.php` - documents encrypted fields and encryption toggle.
- `.env.example` - documents `APP_PREVIOUS_KEYS` and `ENCRYPTION_ENABLED`.

---

## 1. Encryption vs Hashing

Encryption and hashing solve different problems:

| Topic | Encryption | Hashing |
|---|---|---|
| Reversible? | Yes, with the app key | No |
| Use for | Data the app must read again | Passwords and token digests |
| Laravel API | `Crypt::encryptString()` | `Hash::make()` |
| Verification | `Crypt::decryptString()` | `Hash::check()` |

FreelanceFlow encrypts notes because users need to read them again:

```php
$client->notes = 'Private billing note';

echo $client->fresh()->notes;
// Private billing note
```

FreelanceFlow hashes passwords because passwords should never be recoverable:

```php
protected function casts(): array
{
    return [
        'password' => 'hashed',
    ];
}
```

Never encrypt passwords. If encrypted, a leaked `APP_KEY` makes every password recoverable.

---

## 2. Encrypted Attribute Cast

The custom cast lives in `app/Casts/AsEncryptedString.php`.

```php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AsEncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
```

The fallback in `get()` is intentional. During deployment, old rows may still contain plain text. Returning the raw value keeps the application readable while the backfill migration encrypts existing data.

---

## 3. Encrypt Client And Invoice Notes

`Client` now casts `notes` through the encrypted string cast:

```php
use App\Casts\AsEncryptedString;

protected $casts = [
    'notes' => AsEncryptedString::class,
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
];
```

`Invoice` uses the same pattern:

```php
use App\Casts\AsEncryptedString;

protected $casts = [
    'notes' => AsEncryptedString::class,
    'line_items' => 'array',
    'subtotal' => 'decimal:2',
    'tax_rate' => 'decimal:2',
    'tax_amount' => 'decimal:2',
    'total' => 'decimal:2',
    'issued_at' => 'date',
    'due_at' => 'date',
    'paid_at' => 'date',
];
```

The database still stores a `text` column. The encrypted payload is longer than the original note, so `string` columns are too small for this job.

---

## 4. Backfill Existing Notes Safely

New writes are encrypted automatically, but old rows need a one-time migration.

`database/migrations/2026_06_13_000000_encrypt_existing_notes.php` processes rows with `chunkById(500)`:

```php
DB::table($table)
    ->whereNotNull('notes')
    ->where('notes', '!=', '')
    ->orderBy('id')
    ->chunkById(500, function ($rows) use ($table): void {
        foreach ($rows as $row) {
            if ($this->isEncryptedString($row->notes)) {
                continue;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update(['notes' => Crypt::encryptString($row->notes)]);
        }
    });
```

This is the scalable part:

- It avoids loading all clients or invoices into memory.
- It uses raw query builder reads so model casts do not hide the stored value.
- It skips rows already encrypted, so the migration is safe to retry.
- It decrypts in `down()` for local rollbacks.

Run it with:

```bash
php artisan migrate
```

---

## 5. Redact Sensitive Logs

Encryption protects the database. Log redaction protects files, observability tools, and Slack/Sentry-style exports.

`app/Services/Logger.php` now redacts sensitive values before every log write:

```php
private array $redactedKeys = [
    'authorization',
    'card_number',
    'cvv',
    'notes',
    'password',
    'password_confirmation',
    'secret',
    'stripe_payment_intent_id',
    'stripe_payment_status',
    'token',
];
```

The sanitizer is recursive, so nested payloads are covered too:

```php
private function sanitize(array $context): array
{
    foreach ($context as $key => $value) {
        if ($this->shouldRedact((string) $key)) {
            $context[$key] = '[REDACTED]';

            continue;
        }

        if (is_array($value)) {
            $context[$key] = $this->sanitize($value);
        }
    }

    return $context;
}
```

That means this call:

```php
app(Logger::class)->info('Incoming payload', [
    'notes' => 'Private note',
    'payload' => ['api_token' => 'plain-token'],
]);
```

is written with both sensitive values replaced by `[REDACTED]`.

---

## 6. SensitiveString Value Object

`SensitiveString` is useful when code needs to carry a real value internally but avoid accidental display:

```php
$secret = SensitiveString::from('private text');

$secret->reveal();     // private text
(string) $secret;      // [REDACTED]
json_encode($secret);  // "[REDACTED]"
```

This does not replace encryption. It is a defense-in-depth helper for logging, debugging, and serialization boundaries.

---

## 7. Key Rotation

Laravel encryption depends on `APP_KEY`. If the key changes without planning, existing encrypted values cannot be decrypted.

Laravel supports previous keys:

```env
APP_KEY=base64:new-key
APP_PREVIOUS_KEYS=base64:old-key,base64:older-key
```

During a rotation:

1. Add the old key to `APP_PREVIOUS_KEYS`.
2. Deploy the new `APP_KEY`.
3. Run a re-encryption/backfill process so stored payloads are written with the new key.
4. Remove previous keys only after all encrypted values have been re-written and verified.

All app servers must share the same current key and previous-key list during the rotation window.

---

## 8. Config And Environment

`config/freelanceflow.php` documents encrypted fields:

```php
'encryption' => [
    'enabled' => (bool) env('ENCRYPTION_ENABLED', true),

    'encrypted_fields' => [
        'clients' => ['notes'],
        'invoices' => ['notes'],
    ],
],
```

`.env.example` now includes:

```env
APP_KEY=                          # Run: php artisan key:generate
APP_PREVIOUS_KEYS=                # Comma-separated old APP_KEY values during key rotation

ENCRYPTION_ENABLED=true
```

The `ENCRYPTION_ENABLED` setting is documented for operational visibility. The current cast always encrypts because storing sensitive production notes in plain text should not be a runtime toggle.

---

## 9. Tests

The focused test file is `tests/Feature/EncryptionHashingTest.php`.

It verifies:

- Client notes are encrypted in the database and decrypted through Eloquent.
- Invoice notes are encrypted in the database and decrypted through Eloquent.
- User passwords are hashed and verified with `Hash::check()`.
- Logger redaction covers nested sensitive keys.
- `SensitiveString` masks string, debug, and JSON output.

Run the focused tests:

```bash
php artisan test tests/Feature/EncryptionHashingTest.php
```

Run the full suite:

```bash
php artisan test
```

---

## Quick Recap

Today we improved FreelanceFlow's sensitive-data handling by:

- Encrypting `clients.notes` and `invoices.notes` at rest.
- Keeping password handling one-way with Laravel's `hashed` cast.
- Backfilling old plain-text notes in retry-safe chunks.
- Redacting sensitive log context recursively.
- Adding a `SensitiveString` helper for safe display boundaries.
- Documenting key rotation with `APP_PREVIOUS_KEYS`.
- Adding tests that prove the database, model, logger, and password behavior.

---

## Day 46: Two-Factor Authentication

Tomorrow we add two-factor authentication. Users will be able to enable TOTP, store recovery codes, and verify a second factor during login.
