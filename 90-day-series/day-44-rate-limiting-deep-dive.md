# Day 44: Rate Limiting Deep Dive

> **Series:** FreelanceFlow - Laravel Zero to Hero  
> **Phase:** Advanced APIs  
> **Read time:** 12 min  
> **Level:** Intermediate

Rate limiting is a small route-level feature with a big operational job. It protects credential-like endpoints from brute-force attempts, keeps noisy API clients from overwhelming shared infrastructure, and gives consumers a predictable `429 Too Many Requests` response when they need to back off.

Today we make FreelanceFlow's API rate limiting scalable, configurable, and covered by tests.

---

## What Changed

New file:

- `app/Support/ApiRateLimiters.php` - registers API rate limiters in one testable place.

Modified files:

- `app/Providers/AppServiceProvider.php` - delegates limiter registration to `ApiRateLimiters::register()`.
- `config/freelanceflow.php` - stores rate-limit thresholds.
- `.env.example` - documents environment variables for rate limits and Redis client selection.
- `routes/api.php` - separates token creation, read endpoints, and write endpoints.
- `tests/Feature/Api/RateLimitingTest.php` - verifies throttling behavior.
- `composer.json` / `composer.lock` - include `predis/predis` because local Redis config uses `REDIS_CLIENT=predis`.

---

## 1. Keep Limits In Config

Hardcoded limits are awkward to tune. Production, staging, and local development often need different thresholds.

FreelanceFlow stores API limits in `config/freelanceflow.php`:

```php
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
```

And `.env.example` documents the knobs:

```env
API_RATE_LIMIT_AUTHENTICATED=60
API_RATE_LIMIT_GUEST=30
API_READ_RATE_LIMIT_AUTHENTICATED=120
API_READ_RATE_LIMIT_GUEST=30
TOKEN_CREATION_RATE_LIMIT=5
```

This keeps the code stable while allowing limits to scale with real traffic.

---

## 2. Register Named Limiters In One Place

The limiter definitions live in `app/Support/ApiRateLimiters.php`.

```php
RateLimiter::for('api', function (Request $request) {
    return self::perMinute($request, 'api');
});

RateLimiter::for('token-creation', function (Request $request) {
    return Limit::perMinute(self::limit('token_creation', 'per_minute'))
        ->by($request->ip());
});

RateLimiter::for('api-reads', function (Request $request) {
    return self::perMinute($request, 'api_reads');
});
```

`AppServiceProvider` stays clean:

```php
ApiRateLimiters::register();
```

The helper also casts configured values to integers and provides defaults, so a missing config value does not break bootstrapping.

---

## 3. Choose The Right Rate Limit Key

The most important part of a rate limiter is the key used to count attempts.

For authenticated API requests, FreelanceFlow uses the user ID:

```php
Limit::perMinute($limit)->by('user:'.$request->user()->getAuthIdentifier());
```

That means two users sharing the same office, VPN, or public IP do not block each other.

For public token creation, there is no trusted user yet, so the limiter uses IP:

```php
Limit::perMinute($limit)->by($request->ip());
```

That protects the credential-checking endpoint from repeated token creation attempts.

---

## 4. Apply Different Limits To Different API Surfaces

`routes/api.php` uses three named limiters:

```php
Route::post('tokens/create', [AuthController::class, 'createToken'])
    ->middleware('throttle:token-creation');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('tokens/revoke', [AuthController::class, 'revokeToken'])
        ->middleware('throttle:api');

    Route::middleware('throttle:api-reads')->group(function () {
        Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
        Route::get('tags', [TagController::class, 'index']);
        Route::get('tags/{tag}', [TagController::class, 'show']);
    });

    Route::middleware('throttle:api')->group(function () {
        Route::apiResource('clients', ClientController::class)->except(['index', 'show']);
        Route::apiResource('projects', ProjectController::class)->except(['index', 'show']);
    });
});
```

The policy is intentionally simple:

- Token creation is strict because it checks credentials.
- Reads get a higher limit because dashboards and integrations often poll list endpoints.
- Writes and token revocation use the standard API limit.

---

## 5. Keep Throttled API Responses JSON

The API already uses `ForceJsonResponse`, and `bootstrap/app.php` converts throttling exceptions to a consistent JSON payload:

```php
$e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException =>
    response()->json(['success' => false, 'message' => 'Too many requests.'], 429),
```

Clients receive:

```json
{
  "success": false,
  "message": "Too many requests."
}
```

That predictable response makes API clients easier to test and easier to retry with backoff.

---

## 6. Test Behavior, Not Middleware Names

The rate-limit tests live in `tests/Feature/Api/RateLimitingTest.php`.

They verify:

- Token creation returns `429` after the configured limit is exceeded.
- Authenticated write limits are keyed by user, not by shared IP.
- Read endpoints use `api-reads`, so write throttling does not accidentally block reads.

The tests override configured limits to small numbers:

```php
config(['freelanceflow.rate_limits.api.authenticated_per_minute' => 3]);
```

That keeps tests fast and focused while production can keep realistic limits.

Run the focused API tests:

```bash
php artisan test tests/Feature/Api
```

Run the full suite:

```bash
php artisan test
```

---

## 7. Redis And Predis Notes

Laravel stores rate-limit counters in the cache. In tests, `CACHE_STORE=array` is fine because counters only need to live for one test process.

In production, use a shared cache store such as Redis so counters are consistent across multiple app servers:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
```

Because this project uses `REDIS_CLIENT=predis`, Composer must include Predis:

```json
"predis/predis": "^3.5"
```

Without that package, Laravel fails while creating the Redis connection:

```text
Class "Predis\Client" not found
```

After changing Redis or cache configuration, clear Laravel's cached bootstrap files:

```bash
php artisan optimize:clear
```

---

## Quick Recap

Today we improved API rate limiting by:

- Moving rate-limit thresholds into config and `.env.example`.
- Registering named API limiters in `ApiRateLimiters`.
- Separating token, read, and write endpoint limits.
- Keying authenticated limits by user ID for shared-IP fairness.
- Keeping API throttling errors JSON-first.
- Using Redis-compatible cache counters for production scale.
- Installing Predis for `REDIS_CLIENT=predis`.
- Adding feature tests that prove behavior, not just implementation details.
