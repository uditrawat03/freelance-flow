# Day 25 — API Resources & Collections Polish

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"Yesterday we built a working API. Today we make it production-ready. Consistent error responses, rate limiting, a TagResource, forced JSON headers, and a clean API structure that handles every edge case gracefully. The difference between a prototype API and one you can actually give to a client."*

---

## Where We Are

The FreelanceFlow API has working endpoints for clients and projects, Sanctum token auth, and JSON Resource classes. But it has rough edges:

- Unauthenticated requests return an HTML login redirect instead of a JSON error
- No rate limiting — the API can be hammered without restriction
- Tags have no Resource class
- Error responses are inconsistent across endpoints
- No `Accept: application/json` enforcement

Today we fix all of these.

---

## What We Are Building Today

1. **`AcceptJson` middleware** — force JSON responses on every API request
2. **Consistent error responses** — 401, 403, 404, 422, 500
3. **`TagResource`** — complete the resource layer
4. **API rate limiting** — per-token and per-IP throttling
5. **API response envelope** — standardise the shape of every response
6. **Global exception handling** — clean JSON errors for unhandled exceptions

---

## Step 1 — Force JSON on All API Requests

Without this, an unauthenticated request to a protected route returns a 302 redirect to `/login` — HTML, not JSON. Any API client that gets HTML instead of JSON either crashes or shows garbage to the user.

Create the middleware:

```bash
php artisan make:middleware ForceJsonResponse
```

Open `app/Http/Middleware/ForceJsonResponse.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tell Laravel this request expects JSON
        // This ensures validation errors, auth errors, and 404s
        // all return JSON instead of HTML redirects
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
```

Register it on all API routes in `bootstrap/app.php` (Laravel 11+) or `app/Http/Kernel.php` (Laravel 10):

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\ForceJsonResponse::class,
    ]);
})
```

Or add directly to `routes/api.php` as a group middleware:

```php
// routes/api.php
Route::middleware(['api', \App\Http\Middleware\ForceJsonResponse::class])
    ->prefix('v1')
    ->group(function () {
        // ... all API routes
    });
```

Now unauthenticated requests return:

```json
{
  "message": "Unauthenticated."
}
```

With status `401`. Clean, consistent, parseable.

---

## Step 2 — TagResource

Complete the resource layer with a Tag resource:

```bash
php artisan make:resource TagResource
```

Open `app/Http/Resources/TagResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'slug'   => $this->slug,
            'colour' => $this->colour,

            // Conditionally include project count if loaded
            'projects_count' => $this->whenCounted('projects'),
        ];
    }
}
```

Update `ProjectResource` to use `TagResource`:

```php
// app/Http/Resources/ProjectResource.php
'tags' => TagResource::collection($this->whenLoaded('tags')),
```

Add a Tags endpoint to the API routes:

```php
// routes/api.php
use App\Http\Controllers\Api\V1\TagController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // ... existing routes

    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{tag}', [TagController::class, 'show']);
});
```

Create the tag controller:

```bash
php artisan make:controller Api/V1/TagController
```

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::withCount('projects')
                   ->orderBy('name')
                   ->get();

        return TagResource::collection($tags);
    }

    public function show(Tag $tag): TagResource
    {
        $tag->loadCount('projects');

        return new TagResource($tag);
    }
}
```

---

## Step 3 — API Rate Limiting

Rate limiting prevents API abuse — a single token hammering the API with thousands of requests, scraping all client data, or brute-forcing endpoints.

Define rate limiters in `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Standard API rate limit: 60 requests per minute per token/IP
    RateLimiter::for('api', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(60)->by($request->user()->id)
            : Limit::perMinute(30)->by($request->ip());
    });

    // Strict limit for token creation: 5 per minute per IP
    // Prevents brute-force credential attacks
    RateLimiter::for('token-creation', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // Relaxed limit for read-heavy endpoints: 120 per minute
    RateLimiter::for('api-reads', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(30)->by($request->ip());
    });
}
```

Apply the limiters to routes:

```php
// routes/api.php
Route::prefix('v1')->group(function () {

    // Token creation — strict limit
    Route::post('/tokens/create', [AuthController::class, 'createToken'])
         ->middleware('throttle:token-creation');

});

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::post('/tokens/revoke', [AuthController::class, 'revokeToken']);

    // Read endpoints get a more relaxed limit
    Route::get('clients', [ClientController::class, 'index'])
         ->withoutMiddleware('throttle:api')
         ->middleware('throttle:api-reads');

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{tag}', [TagController::class, 'show']);

});
```

When the rate limit is exceeded, Laravel returns:

```json
HTTP 429 Too Many Requests
Retry-After: 47

{
  "message": "Too Many Attempts."
}
```

The `Retry-After` header tells the client how many seconds to wait before retrying. Laravel sets this automatically.

---

## Step 4 — Standardise Error Responses

Right now different error types return slightly different JSON structures. Create a consistent error response helper.

Create `app/Http/Resources/ApiResponse.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed  $data,
        string $message = 'Success',
        int    $status  = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(
        string $message,
        int    $status  = 400,
        array  $errors  = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, 404);
    }

    public static function forbidden(string $message = 'This action is not authorized.'): JsonResponse
    {
        return self::error($message, 403);
    }

    public static function created(mixed $data, string $message = 'Created successfully.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
```

Update the `ClientController` to use consistent responses:

```php
use App\Http\Resources\ApiResponse;

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]);

    $client = Client::create($validated);

    return ApiResponse::created(
        new ClientResource($client),
        'Client created successfully.'
    );
}

public function destroy(Client $client): JsonResponse
{
    $client->delete();

    return ApiResponse::success(null, 'Client deleted successfully.');
}
```

---

## Step 5 — Global Exception Handling for the API

When an unhandled exception occurs in the API, it should return a clean JSON error — not an HTML stack trace or an empty response.

Open `bootstrap/app.php` and add API exception handling:

```php
->withExceptions(function (Exceptions $exceptions) {

    // Handle all exceptions that occur in API routes
    $exceptions->render(function (\Throwable $e, $request) {
        if ($request->is('api/*')) {
            return match (true) {
                $e instanceof \Illuminate\Auth\AuthenticationException =>
                    response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401),

                $e instanceof \Illuminate\Auth\Access\AuthorizationException =>
                    response()->json(['success' => false, 'message' => 'Forbidden.'], 403),

                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException =>
                    response()->json(['success' => false, 'message' => 'Resource not found.'], 404),

                $e instanceof \Illuminate\Validation\ValidationException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors'  => $e->errors(),
                    ], 422),

                $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException =>
                    response()->json(['success' => false, 'message' => 'Too many requests.'], 429),

                default => response()->json([
                    'success' => false,
                    'message' => app()->isProduction()
                        ? 'An unexpected error occurred.'
                        : $e->getMessage(),
                ], 500),
            };
        }
    });

})
```

Now every possible error in the API returns a consistent JSON structure. In production the 500 message is generic — no stack traces leak to API consumers.

---

## Step 6 — Add Pagination to All List Endpoints

Update the `ProjectController@index` to include proper pagination with `per_page` control and sane defaults:

```php
public function index(Request $request): AnonymousResourceCollection
{
    $request->validate([
        'per_page'  => 'nullable|integer|min:1|max:100',
        'status'    => 'nullable|in:draft,active,on_hold,completed,cancelled',
        'client_id' => 'nullable|exists:clients,id',
        'overdue'   => 'nullable|boolean',
    ]);

    $projects = Project::query()
        ->with(['client', 'tags'])
        ->when($request->status, fn ($q) => $q->status($request->status))
        ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
        ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
        ->latest()
        ->paginate($request->integer('per_page', 15));

    return ProjectResource::collection($projects);
}
```

The `max:100` on `per_page` prevents a client from requesting all 5,000 records in one call.

---

## Step 7 — Final Route File

Here is the complete, clean `routes/api.php` after all changes:

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

Route::middleware([ForceJsonResponse::class])->prefix('v1')->group(function () {

    // --- Public ---
    Route::post('tokens/create', [AuthController::class, 'createToken'])
         ->middleware('throttle:token-creation');

    // --- Protected ---
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('tokens/revoke', [AuthController::class, 'revokeToken']);

        Route::middleware('throttle:api')->group(function () {
            Route::apiResource('clients', ClientController::class);
            Route::apiResource('projects', ProjectController::class);
            Route::get('tags', [TagController::class, 'index']);
            Route::get('tags/{tag}', [TagController::class, 'show']);
        });

    });

});
```

---

## Test the Polished API

```bash
# 1. Get a token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/tokens/create \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@freelanceflow.test","password":"password","device_name":"test"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

echo "Token: $TOKEN"

# 2. List clients
curl "http://localhost:8000/api/v1/clients?per_page=5" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

# 3. Test rate limit — run 35 times (over the 30/min unauthenticated limit)
for i in {1..35}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
    http://localhost:8000/api/v1/clients)
  echo "Request $i: $STATUS"
done

# 4. Test unauthenticated — should return 401 JSON not HTML
curl http://localhost:8000/api/v1/clients \
  -H "Accept: application/json"
```

---

## What We Learned Today

- **`ForceJsonResponse` middleware** — sets `Accept: application/json` on every API request so Laravel always returns JSON, never an HTML redirect
- **Multiple rate limiters** — `api`, `api-reads`, `token-creation` — different limits for different endpoint types
- **`Limit::perMinute(60)->by($user->id)`** — rate limits per user ID for authenticated requests, per IP for guests
- **`ApiResponse` helper class** — a static helper for consistent `success`, `error`, `created`, `notFound`, `forbidden` responses
- **Global exception handling in `bootstrap/app.php`** — catch every exception type that can occur in API routes and return the right JSON structure
- **`max:100` on `per_page`** — always cap pagination size. Never let a client request all records in one go
- **`sometimes` vs `nullable` validation** — `sometimes` skips the field if absent (partial update), `nullable` validates as null if absent (full update)
- **Production error messages** — return a generic message in production, full exception message in development. `app()->isProduction()` handles the switch

---

## Day 26 — Invoice Generation with PDF

Tomorrow we build FreelanceFlow's most impressive feature so far — PDF invoice generation. We will design an invoice Blade template, use DomPDF to render it server-side, store the PDF in the file system, let the client download it, and track invoice status (draft, sent, paid, overdue). By the end of Day 26 FreelanceFlow can generate and deliver professional invoices.

See you on Day 26.