# Day 24 — Building a REST API with Sanctum

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"FreelanceFlow lives in the browser right now. But what if you want a mobile app? What if a client wants to pull their project data into their own dashboard? What if you want to integrate with Zapier or Make? That requires an API. Today we build one — authenticated with Sanctum tokens, shaped with JSON Resource classes, and tested end to end."*

---

## What We Are Building Today

1. **Install and configure Sanctum** — token-based API authentication
2. **API routes** — a versioned `routes/api.php` structure
3. **JSON Resource classes** — shape the API response cleanly
4. **Resource Collections** — list responses with metadata
5. **Protected API endpoints** — clients and projects
6. **Token generation endpoint** — issue and revoke tokens
7. **Test the API** with curl and Postman examples

---

## Step 1 — Install Sanctum

Sanctum ships with Laravel by default. Confirm it is in your `composer.json`:

```bash
composer show laravel/sanctum
```

If it is missing:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

The migration creates a `personal_access_tokens` table — one row per issued token.

Open `app/Models/User.php` and confirm the `HasApiTokens` trait is present:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

---

## Step 2 — Structure the API Routes

Open `routes/api.php`. By default Laravel prefixes all routes here with `/api`. We will version our API from day one — `v1` — so future breaking changes get a new version without affecting existing clients:

```
If don't have api.php then create new.
Add this line `api: __DIR__.'/../routes/api.php'` in `bootstrap/app.php`
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withEvents(discover: false)->create();

```

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

// Public routes — no token required
Route::prefix('v1')->group(function () {

    // Token management
    Route::post('/tokens/create', [AuthController::class, 'createToken']);

});

// Protected routes — Sanctum token required
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    Route::post('/tokens/revoke', [AuthController::class, 'revokeToken']);

    // Clients
    Route::apiResource('clients', ClientController::class);

    // Projects
    Route::apiResource('projects', ProjectController::class);

});
```

`apiResource()` registers 5 routes (no `create` or `edit` — those are for HTML forms):

```
GET    /api/v1/clients          → index
POST   /api/v1/clients          → store
GET    /api/v1/clients/{client} → show
PUT    /api/v1/clients/{client} → update
DELETE /api/v1/clients/{client} → destroy
```

Create the controller directory structure:

```bash
mkdir -p app/Http/Controllers/Api/V1
```

---

## Step 3 — Token Authentication Controller

```bash
php artisan make:controller Api/V1/AuthController
```

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a new API token.
     * POST /api/v1/tokens/create
     */
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create a token with optional abilities
        $token = $user->createToken(
            $request->device_name,
            ['clients:read', 'clients:write', 'projects:read', 'projects:write']
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'type'  => 'Bearer',
        ], 201);
    }

    /**
     * Revoke the current token.
     * POST /api/v1/tokens/revoke
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}
```

The `device_name` is how you identify where the token was issued from — "iPhone 15", "Postman", "Zapier integration". It appears in the `personal_access_tokens` table so you can see which devices have access and revoke specific ones.

---

## Step 4 — JSON Resource Classes

Without a Resource class, `return $client` serialises every column including ones you may not want to expose. Resource classes give you explicit control over the API shape.

```bash
php artisan make:resource ClientResource
php artisan make:resource ClientCollection
php artisan make:resource ProjectResource
```

Open `app/Http/Resources/ClientResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'display_name'  => $this->display_name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'company'       => $this->company,
            'status'        => $this->status,
            'status_label'  => $this->status_label,
            'notes'         => $this->notes,

            // Conditionally include project count if loaded
            'projects_count' => $this->whenCounted('projects'),

            // Conditionally include projects if loaded
            'projects' => ProjectResource::collection(
                $this->whenLoaded('projects')
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

Open `app/Http/Resources/ClientCollection.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total'        => $this->total(),
                'per_page'     => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page'    => $this->lastPage(),
                'from'         => $this->firstItem(),
                'to'           => $this->lastItem(),
            ],
            'links' => [
                'first' => $this->url(1),
                'last'  => $this->url($this->lastPage()),
                'prev'  => $this->previousPageUrl(),
                'next'  => $this->nextPageUrl(),
            ],
        ];
    }
}
```

Open `app/Http/Resources/ProjectResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'status'          => $this->status,
            'status_label'    => $this->status_label,
            'budget'          => $this->budget,
            'formatted_budget'=> $this->formatted_budget,
            'deadline'        => $this->deadline?->toDateString(),
            'is_overdue'      => $this->is_overdue,

            // Include client only if loaded
            'client' => new ClientResource($this->whenLoaded('client')),

            // Include tags only if loaded
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

**`whenLoaded()`** is one of the most important Resource patterns. It only includes the relationship data if it was eager-loaded. If the controller did not call `with('client')`, the `client` key is omitted entirely — no N+1, no null value confusion.

**`whenCounted()`** works the same way for `withCount()` calls — only includes the count if it was loaded.

---

## Step 5 — Build the Client API Controller

```bash
php artisan make:controller Api/V1/ClientController --api
```

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientCollection;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * GET /api/v1/clients
     * List all clients with optional filtering and pagination.
     */
    public function index(Request $request): ClientCollection
    {
        $clients = Client::query()
            ->withCount('projects')
            ->when($request->status, fn ($q) => $q->status($request->status))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('company', 'like', "%{$request->search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return new ClientCollection($clients);
    }

    /**
     * POST /api/v1/clients
     * Create a new client.
     */
    public function store(Request $request): ClientResource
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:clients,email',
            'phone'   => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
            'status'  => 'nullable|in:active,inactive,lead',
        ]);

        $client = Client::create($validated);

        return new ClientResource($client);
    }

    /**
     * GET /api/v1/clients/{client}
     * Show a single client with their projects.
     */
    public function show(Client $client): ClientResource
    {
        $client->load(['projects' => fn ($q) => $q->with('tags')->latest()]);

        return new ClientResource($client);
    }

    /**
     * PUT /api/v1/clients/{client}
     * Update a client.
     */
    public function update(Request $request, Client $client): ClientResource
    {
        $validated = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'email'   => "sometimes|required|email|unique:clients,email,{$client->id}",
            'phone'   => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
            'status'  => 'nullable|in:active,inactive,lead',
        ]);

        $client->update($validated);

        return new ClientResource($client->fresh());
    }

    /**
     * DELETE /api/v1/clients/{client}
     * Soft delete a client.
     */
    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully.',
        ], 200);
    }
}
```

`sometimes` on update validation — the rule only fires if the field is present in the request. This enables partial updates (PATCH-style behaviour) through a PUT endpoint without requiring every field.

---

## Step 6 — Build the Project API Controller

```bash
php artisan make:controller Api/V1/ProjectController --api
```

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with(['client', 'tags'])
            ->when($request->status, fn ($q) => $q->status($request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    public function store(Request $request): ProjectResource
    {
        $validated = $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,active,on_hold,completed,cancelled',
            'budget'      => 'nullable|numeric|min:0',
            'deadline'    => 'nullable|date',
            'tag_ids'     => 'nullable|array',
            'tag_ids.*'   => 'exists:tags,id',
        ]);

        $project = Project::create($validated);

        if (! empty($validated['tag_ids'])) {
            $project->tags()->sync($validated['tag_ids']);
        }

        return new ProjectResource($project->load(['client', 'tags']));
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load(['client', 'tags']));
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|required|in:draft,active,on_hold,completed,cancelled',
            'budget'      => 'nullable|numeric|min:0',
            'deadline'    => 'nullable|date',
            'tag_ids'     => 'nullable|array',
            'tag_ids.*'   => 'exists:tags,id',
        ]);

        $project->update($validated);

        if (array_key_exists('tag_ids', $validated)) {
            $project->tags()->sync($validated['tag_ids'] ?? []);
        }

        return new ProjectResource($project->fresh()->load(['client', 'tags']));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }
}
```

---

## Step 7 — Test the API

### Get a token

```bash
curl -X POST http://localhost:8000/api/v1/tokens/create \
  -H "Content-Type: application/json" \
  -d '{
    "email": "demo@freelanceflow.test",
    "password": "password",
    "device_name": "curl-test"
  }'

# Response:
# {"token":"1|abc123...","type":"Bearer"}
```

### List clients

```bash
curl http://localhost:8000/api/v1/clients \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

### Create a client

```bash
curl -X POST http://localhost:8000/api/v1/clients \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "API Test Client",
    "email": "api@test.com",
    "status": "active"
  }'
```

### Filter and paginate

```bash
# Active clients, page 2, 5 per page
curl "http://localhost:8000/api/v1/clients?status=active&per_page=5&page=2" \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
```

---

## API Response Examples

### GET /api/v1/clients

```json
{
  "data": [
    {
      "id": 1,
      "name": "Jane Smith",
      "display_name": "Jane Smith (Acme Corp)",
      "email": "jane@acme.com",
      "phone": "+91 98765 43210",
      "company": "Acme Corp",
      "status": "active",
      "status_label": "Active",
      "notes": null,
      "projects_count": 3,
      "created_at": "2026-04-25T10:00:00.000000Z",
      "updated_at": "2026-04-25T10:00:00.000000Z"
    }
  ],
  "meta": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 15
  },
  "links": {
    "first": "http://localhost:8000/api/v1/clients?page=1",
    "last": "http://localhost:8000/api/v1/clients?page=4",
    "prev": null,
    "next": "http://localhost:8000/api/v1/clients?page=2"
  }
}
```

### Validation error response (automatic)

```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```

Laravel automatically returns 422 with this structure when validation fails in an API context — no extra code needed.

---

## Token Abilities

Sanctum supports fine-grained token abilities (scopes). We created tokens with abilities on Day 24. Check abilities in controllers:

```php
// Check if the current token has a specific ability
if ($request->user()->tokenCan('clients:write')) {
    // allow write operations
}

// Middleware to enforce abilities on routes
Route::post('/clients', [ClientController::class, 'store'])
    ->middleware('ability:clients:write');
```

---

## API Reference

```php
// Issue a token
$token = $user->createToken('device-name', ['clients:read']);
$token->plainTextToken; // the string to send in the Authorization header

// Revoke current token
$request->user()->currentAccessToken()->delete();

// Revoke all tokens
$user->tokens()->delete();

// Check ability
$request->user()->tokenCan('clients:write');

// Route protection
->middleware('auth:sanctum')
->middleware('ability:clients:read,projects:read') // requires BOTH
->middleware('abilities:clients:read,projects:read') // requires EITHER

// Resource patterns
new ClientResource($client);                        // single
ClientResource::collection($clients);               // collection
new ClientCollection($clients);                     // custom collection class

// Conditional fields
$this->whenLoaded('projects')    // omit if not eager-loaded
$this->whenCounted('projects')   // omit if not counted
$this->when($condition, $value)  // omit if condition is false
$this->mergeWhen($condition, []) // merge array conditionally
```

---

## What We Learned Today

- **Sanctum** — issues plain-text API tokens stored in `personal_access_tokens`. Authenticated via `Authorization: Bearer {token}` header
- **API versioning** — `Route::prefix('v1')` from day one. Future breaking changes get `v2` without affecting existing API clients
- **`apiResource()`** — registers 5 routes (index, store, show, update, destroy). No `create` or `edit` — those are HTML form routes
- **JSON Resource classes** — explicit control over which columns appear in the API response. Never return raw Eloquent models
- **`whenLoaded()`** — conditionally include relationships in the Resource. Zero N+1 risk. Omits the key entirely if not loaded
- **`whenCounted()`** — conditionally include relationship counts. Same principle
- **`sometimes` validation** — only validates a field if it is present in the request. Enables partial updates through PUT
- **Token abilities** — fine-grained permissions on each issued token. Check with `tokenCan()`, enforce with `ability:` middleware
- **Automatic 422 responses** — Laravel returns structured validation errors automatically in API context. No extra code

---

## Day 25 — API Resources & Collections Polish

Tomorrow we refine the API. We will build a `TagResource`, add consistent error responses across all endpoints, implement API rate limiting to prevent abuse, add `Accept: application/json` middleware to ensure consistent JSON responses, and document the API using route listing and docblock comments so the endpoints are self-describing.

See you on Day 25.
