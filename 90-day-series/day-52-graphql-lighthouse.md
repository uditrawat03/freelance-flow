# Day 52 - GraphQL with Lighthouse

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3 - Advanced**
> **Read time:** 16 min | **Level:** Intermediate

---

The REST API from Days 24-25 is great for stable resource endpoints. GraphQL solves a different problem: clients can ask for exactly the shape of data they need, including related records, without creating a new REST endpoint for every screen.

Today we add a Lighthouse-powered GraphQL API to FreelanceFlow that stays aligned with the existing Laravel app instead of becoming a second business layer.

---

## What We Are Changing Today

### New files

- `graphql/schema.graphql` - the FreelanceFlow GraphQL schema.
- `app/GraphQL/Mutations/Login.php` - Sanctum token login.
- `app/GraphQL/Mutations/Logout.php` - current token revocation.
- `app/GraphQL/Mutations/CreateInvoice.php` - invoice creation through `InvoiceService`.
- `app/GraphQL/Queries/DashboardStats.php` - dashboard metrics through `DashboardService`.
- `app/GraphQL/Queries/CurrentWorkspace.php` - active workspace resolver.
- `tests/Feature/GraphQLApiTest.php` - coverage for auth, tenancy, dashboard stats, and invoice mutation.

### Modified files

- `composer.json` / `composer.lock` - add `nuwave/lighthouse`.
- `config/lighthouse.php` - configure guards, query limits, schema cache, and pagination limits.
- `.env.example` - add Lighthouse environment variables.
- `90-day-series/day-52-graphql-lighthouse.md` - keep the article aligned with the actual implementation.

---

## Step 1 - Install Lighthouse

```bash
composer require nuwave/lighthouse
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=lighthouse-config
php artisan vendor:publish --tag=lighthouse-schema
```

On Windows, this project may need Composer to ignore Horizon's Unix-only extensions during dependency work:

```bash
composer require nuwave/lighthouse --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Lighthouse registers `/graphql` from `config/lighthouse.php`. We do not add a manual route in `routes/web.php`.

---

## Step 2 - Configure Lighthouse for FreelanceFlow

The published config is intentionally large. These are the important changes:

```php
'guards' => ['sanctum', 'web'],

'security' => [
    'max_query_complexity' => env('LIGHTHOUSE_SECURITY_MAX_QUERY_COMPLEXITY', 200),
    'max_query_depth' => env('LIGHTHOUSE_SECURITY_MAX_QUERY_DEPTH', 12),
    'disable_introspection' => (bool) env('LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION', false)
        ? GraphQL\Validator\Rules\DisableIntrospection::ENABLED
        : GraphQL\Validator\Rules\DisableIntrospection::DISABLED,
],

'pagination' => [
    'default_count' => 15,
    'max_count' => 100,
],
```

Why this matters:

- `sanctum` lets API clients authenticate with bearer tokens.
- `web` keeps browser-session GraphQL requests useful in local development.
- Query depth and complexity limits protect the server from huge nested requests.
- Pagination caps prevent clients from requesting unbounded lists.
- Lighthouse relation batching remains enabled, so nested relation fields avoid classic N+1 behavior.

---

## Step 3 - Define the Schema

The schema lives in `graphql/schema.graphql`.

Key design choices:

- Money fields use `Float` to avoid adding a second scalar package for this lesson.
- Lists are paginated with `@paginate(..., maxCount: 100)`.
- Relations use Lighthouse directives such as `@belongsTo`, `@hasMany`, and `@belongsToMany`.
- Workspace scoping is not duplicated in GraphQL. `Client`, `Project`, and `Invoice` already use the `BelongsToWorkspace` global scope, so GraphQL queries inherit the same tenant boundary as the web UI and REST API.

Example query:

```graphql
query {
  clients(first: 5) {
    data {
      id
      name
      status_label
      projects_count
      projects {
        id
        name
        formatted_budget
      }
    }
    paginatorInfo {
      total
      currentPage
      perPage
    }
  }
}
```

---

## Step 4 - Login and Logout Mutations

`login` validates credentials and returns a Sanctum token:

```graphql
mutation {
  login(
    email: "demo@freelanceflow.test"
    password: "password"
    device_name: "mobile-app"
  ) {
    token
    type
    user {
      id
      email
    }
  }
}
```

Authenticated requests then send:

```http
Authorization: Bearer 1|your-token-here
Accept: application/json
```

`logout` deletes only the current access token:

```graphql
mutation {
  logout
}
```

---

## Step 5 - Dashboard Stats Resolver

The GraphQL resolver delegates to `DashboardService`:

```php
class DashboardStats
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        return $this->dashboardService->stats();
    }
}
```

That keeps GraphQL, Livewire, and future clients on the same cached metrics path.

```graphql
query {
  dashboardStats {
    total_clients
    active_projects
    unpaid_invoices
    overdue_invoices
    total_revenue
    revenue_this_month
  }
}
```

---

## Step 6 - Create Invoice Mutation

The mutation validates input, verifies the optional project belongs to the selected client, and then calls `InvoiceService::create()`.

```graphql
mutation {
  createInvoice(input: {
    client_id: "1"
    project_id: "3"
    tax_rate: 18
    issued_at: "2026-06-14"
    due_at: "2026-06-30"
    line_items: [
      { description: "Discovery", quantity: 2, rate: 1000 }
      { description: "Build", quantity: 1, rate: 3000 }
    ]
  }) {
    id
    number
    status
    subtotal
    tax_amount
    total
  }
}
```

Because invoice creation goes through the existing service and repository, totals, invoice numbers, workspace assignment, user assignment, and cache invalidation stay consistent.

---

## Step 7 - Environment Variables

Add these to `.env.example`:

```env
LIGHTHOUSE_SCHEMA_CACHE_ENABLE=false
LIGHTHOUSE_QUERY_CACHE_ENABLE=true
LIGHTHOUSE_VALIDATION_CACHE_ENABLE=true
LIGHTHOUSE_SECURITY_MAX_QUERY_COMPLEXITY=200
LIGHTHOUSE_SECURITY_MAX_QUERY_DEPTH=12
LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=false
```

Production recommendation:

```env
LIGHTHOUSE_SCHEMA_CACHE_ENABLE=true
LIGHTHOUSE_VALIDATION_CACHE_ENABLE=true
LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true
```

---

## Step 8 - Tests

The feature test covers:

- Login returns a Sanctum bearer token.
- Client pagination is workspace-scoped.
- Invoice creation uses the existing service and calculates totals.
- Dashboard stats reuse workspace-scoped metrics.

Run the focused test:

```bash
php artisan test tests/Feature/GraphQLApiTest.php
```

Run the full suite:

```bash
php artisan test
```

---

## Scalability Checklist

- Keep reads paginated and capped at 100 records.
- Keep query depth and complexity limits enabled.
- Let Lighthouse batch relation loading for nested queries.
- Reuse existing services for mutations instead of writing business rules in resolvers.
- Keep tenant scoping in Eloquent global scopes so every interface shares the same data boundary.
- Cache the schema in production.
- Cache parsed and validated queries when the cache store supports it.
- Disable introspection in production unless trusted tooling requires it.

---

## Complete File Change Summary

| File | Change type | What changed |
|---|---|---|
| `composer.json` / `composer.lock` | Modified | Added `nuwave/lighthouse` |
| `graphql/schema.graphql` | New | Schema with auth, workspace data, paginated resources, relations, dashboard stats, and invoice mutation |
| `config/lighthouse.php` | New/modified | Published config with guards, pagination caps, and security limits |
| `app/GraphQL/Mutations/Login.php` | New | Credential validation and Sanctum token creation |
| `app/GraphQL/Mutations/Logout.php` | New | Current token revocation |
| `app/GraphQL/Mutations/CreateInvoice.php` | New | Validated invoice mutation using `InvoiceService` |
| `app/GraphQL/Queries/DashboardStats.php` | New | Cached dashboard metrics through `DashboardService` |
| `app/GraphQL/Queries/CurrentWorkspace.php` | New | Active workspace resolver |
| `.env.example` | Modified | Lighthouse cache/security variables |
| `tests/Feature/GraphQLApiTest.php` | New | GraphQL auth, tenancy, mutation, and dashboard tests |

---

## What We Learned Today

- Lighthouse turns a Laravel app into a GraphQL server with schema directives.
- `@paginate`, `@find`, `@all`, and relation directives cover a lot of read-side API work.
- Custom resolvers are best saved for business behavior, not basic CRUD plumbing.
- Sanctum tokens work cleanly with GraphQL when Lighthouse guards are configured.
- Scalability in GraphQL starts with limits: max depth, max complexity, capped pagination, schema caching, and relation batching.
- Workspace scoping belongs in one durable layer. In FreelanceFlow, that layer is Eloquent's global workspace scope.

---

## Day 53 - Laravel Octane with FrankenPHP

Next, we supercharge FreelanceFlow's performance with Laravel Octane and FrankenPHP. Octane keeps the application bootstrapped in memory between requests, reducing PHP startup overhead and making high-concurrency workloads much cheaper.
