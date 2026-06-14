# Day 51 - Inertia.js Introduction

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3:** Advanced  
> **Read time:** 18 min | **Level:** Intermediate

---

Livewire keeps most interaction logic in PHP. Inertia lets Laravel render full Vue pages without building a separate API. Both fit FreelanceFlow: Livewire remains excellent for CRUD workflows, while Inertia is a strong choice for analytics screens, reporting views, and richer client-side state.

Today we add an Inertia + Vue project analytics page that coexists with the existing Livewire app.

---

## What We Built

### New files

- `app/Http/Controllers/ProjectAnalyticsController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/views/layouts/inertia.blade.php`
- `resources/js/app-inertia.js`
- `resources/js/Components/AppLayout.vue`
- `resources/js/Components/StatusBadge.vue`
- `resources/js/Pages/Projects/Analytics.vue`
- `tests/Feature/ProjectAnalyticsTest.php`

### Modified files

- `composer.json`
- `package.json`
- `vite.config.js`
- `bootstrap/app.php`
- `routes/web.php`
- `resources/css/app.css`
- `resources/views/livewire/projects/edit.blade.php`

---

## Step 1 - Install Inertia and Vue

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3 vue @vitejs/plugin-vue
```

On Windows, this project may need Composer to ignore Horizon's Linux-only extensions:

```bash
composer require inertiajs/inertia-laravel --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

The installed versions in this project are:

- `inertiajs/inertia-laravel`: `^3.1`
- `@inertiajs/vue3`: `^3.4.0`
- `vue`: `^3.5.38`
- `@vitejs/plugin-vue`: `^6.0.7`

---

## Step 2 - Add the Inertia Root Template Inside the App Wrapper

Inertia needs a Blade root view, but it should not feel like a separate application. FreelanceFlow's Inertia root uses the same navbar, sidebar, notification toast, Flux scripts, and page container as the original app wrapper.

```blade
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/app-inertia.js'])
@fluxAppearance
@inertiaHead

@include('partials.navbar')

<div class="flex pt-16">
    @auth
        @include('partials.sidebar')
    @endauth

    <main class="min-h-[calc(100vh-64px)] flex-1 bg-background">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <x-flash-message />
            @inertia
        </div>
    </main>
</div>

<livewire:notification />
@fluxScripts
```

`@inertia` mounts the Vue page inside the normal product shell. `@inertiaHead` lets Vue pages control the document title and meta tags. Loading both `app.js` and `app-inertia.js` keeps existing Echo/Livewire/Flux behavior available while Vue owns only the page content.

---

## Step 3 - Bootstrap Vue

`resources/js/app-inertia.js` creates the Vue app and resolves pages from `resources/js/Pages`.

```js
createInertiaApp({
    title: (title) => (title ? `${title} - FreelanceFlow` : 'FreelanceFlow'),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

The Vite config now has three inputs:

- `resources/css/app.css`
- `resources/js/app.js` for Livewire
- `resources/js/app-inertia.js` for Inertia/Vue

It also registers `@vitejs/plugin-vue` and the `@` alias for `resources/js`.

---

## Step 4 - Register Inertia Middleware

`app/Http/Middleware/HandleInertiaRequests.php` sets the Inertia root view and shares small global props:

- authenticated user
- current route name
- flash messages
- navigation URLs
- current workspace

Keep shared props lean. Data returned from `share()` travels with every Inertia response. Expensive page data belongs in the controller.

The middleware is appended to the web group in `bootstrap/app.php`:

```php
$middleware->appendToGroup('web', [
    \App\Http\Middleware\HandleInertiaRequests::class,
    \App\Http\Middleware\SetUserLocale::class,
    \App\Http\Middleware\EnsureWorkspaceSelected::class,
]);
```

---

## Step 5 - Add the Analytics Route

The analytics route lives beside the existing project routes:

```php
Route::get('/projects/{project}/analytics', [ProjectAnalyticsController::class, 'show'])
    ->name('projects.analytics');
```

It must appear before `/projects/{project}` so Laravel does not treat `analytics` as a project route segment.

---

## Step 6 - Build a Scalable Controller

The first draft of this article loaded all invoices and summed them in PHP. That works for demos but does not scale.

The final controller uses SQL aggregates for totals and returns only the first 50 invoices for the page:

```php
$invoiceQuery = $project->invoices();

$invoiceCount = (clone $invoiceQuery)->count();
$totalInvoiced = (float) (clone $invoiceQuery)->sum('total');
$totalPaid = (float) (clone $invoiceQuery)->where('status', 'paid')->sum('total');
$totalOutstanding = (float) (clone $invoiceQuery)
    ->whereIn('status', ['sent', 'overdue'])
    ->sum('total');

$invoices = (clone $invoiceQuery)
    ->select(['id', 'project_id', 'number', 'status', 'total', 'issued_at', 'due_at'])
    ->orderByDesc('issued_at')
    ->orderByDesc('id')
    ->limit(50)
    ->get();
```

This keeps the response stable even when a project has hundreds or thousands of invoices.

The controller also authorizes access:

```php
Gate::authorize('view', $project);
```

Never expose analytics data without checking ownership or workspace access.

---

## Step 7 - Shape Data for Vue

Inertia props should be plain arrays, not raw Eloquent models. The controller sends only the fields the page needs:

```php
return Inertia::render('Projects/Analytics', [
    'project' => [
        'id' => $project->id,
        'name' => $project->name,
        'status' => $project->status,
        'formatted_budget' => $this->formatCurrency($project->budget),
        'client' => [
            'id' => $project->client->id,
            'name' => $project->client->name,
        ],
        'urls' => [
            'client' => route('clients.show', $project->client),
            'edit' => route('projects.edit', $project),
        ],
    ],
    'invoices' => $invoices->map(fn ($invoice) => [
        'id' => $invoice->id,
        'number' => $invoice->number,
        'status' => $invoice->status,
        'formatted_total' => $this->formatCurrency($invoice->total),
        'issued_at' => $invoice->issued_at?->toDateString(),
        'due_at' => $invoice->due_at?->toDateString(),
    ])->values(),
    'stats' => [
        'invoice_count' => $invoiceCount,
        'has_more_invoices' => $invoiceCount > 50,
    ],
]);
```

Passing URLs from Laravel avoids requiring Ziggy just to generate links in Vue.

---

## Step 8 - Build Vue Components

`AppLayout.vue` is intentionally thin now:

```vue
<template>
    <slot />
</template>
```

The app chrome belongs to Blade because the existing navbar/sidebar already use Blade, Livewire, Flux, Alpine, and Laravel route helpers. Keeping that wrapper in Blade prevents the analytics page from looking detached from the rest of the product.

`StatusBadge.vue` centralizes status styling for projects and invoices. This keeps status colors consistent and prevents every page from inventing its own badge map.

`Projects/Analytics.vue` renders:

- project header
- budget, invoiced, paid, and outstanding metrics
- bounded invoice list
- tag chips
- edit and client links

Links from the Inertia page back to Livewire routes use plain anchors:

```vue
<a :href="project.urls.edit">Edit</a>
<a :href="project.urls.client">Back to {{ project.client.name }}</a>
```

Use Inertia's `<Link>` only for routes that return Inertia responses. When navigating from Vue to a Livewire page, a normal `<a>` performs the clean full-page handoff and avoids treating Livewire pages as Inertia visits.

---

## Step 9 - Link From the Livewire Edit Page

The existing Livewire project edit page now links to the Inertia analytics page:

```blade
<a href="{{ route('projects.analytics', $project) }}" class="text-sm font-semibold text-primary hover:text-primary-hover">
    View analytics
</a>
```

Use a normal anchor here. Livewire and Inertia can live in the same Laravel app, but each system manages its own navigation lifecycle.

---

## Step 10 - Test the Integration

`tests/Feature/ProjectAnalyticsTest.php` verifies the important behavior:

- the route returns a valid Inertia page
- the component is `Projects/Analytics`
- aggregate stats are correct
- the invoice payload is capped at 50 rows
- `has_more_invoices` becomes true when additional rows exist

```php
$this->get(route('projects.analytics', $project))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('Projects/Analytics')
        ->where('stats.invoice_count', 56)
        ->where('stats.total_invoiced', 'INR 55,500.00')
        ->where('stats.has_more_invoices', true)
        ->has('invoices', 50)
    );
```

Run:

```bash
php artisan test
npm run build
```

---

## Livewire vs Inertia

| Use case | Livewire | Inertia + Vue |
|---|---|---|
| CRUD forms | Excellent | Good |
| Server-side validation | Excellent | Good |
| Searchable tables | Excellent | Good |
| Rich client-side state | Possible, but heavy | Excellent |
| Analytics dashboards | Good | Excellent |
| Chart libraries | Possible | Natural |
| PHP-first team | Natural | Learning curve |
| Vue/React team | Learning curve | Natural |

FreelanceFlow now uses both. Livewire remains the default for CRUD screens. Inertia is available for pages that benefit from a richer JavaScript component model.

---

## Scalability Notes

- Use SQL aggregates instead of loading full relationships for totals.
- Select only the columns the Vue page needs.
- Cap first-screen lists and expose `has_more_*` flags.
- Share only lightweight global props from Inertia middleware.
- Pass Laravel-generated URLs as props unless the project intentionally adopts Ziggy.
- Keep Livewire and Inertia entry points separate so each stack can evolve independently.
- Mount Inertia pages inside the original Blade app wrapper when the page should remain part of the main product experience.
- Use plain anchors when an Inertia page links to Livewire routes.

---

## What We Learned

- Inertia lets Laravel controllers render Vue pages without building a separate API.
- `@inertia` can mount the Vue page inside the existing Blade app chrome.
- `createInertiaApp()` resolves Vue pages and wires the Inertia plugin.
- Inertia shared props are powerful, but they must stay small.
- Analytics pages should aggregate in SQL and send bounded payloads.
- Livewire and Inertia can coexist cleanly in one Laravel app when cross-stack navigation uses the right link type.

---

## Day 52 - GraphQL with Lighthouse

Next, we add a GraphQL API with Laravel Lighthouse so API clients can request exactly the fields they need.
