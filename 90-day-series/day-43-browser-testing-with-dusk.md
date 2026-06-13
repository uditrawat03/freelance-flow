# Day 43 - Browser Testing with Dusk

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3 - Advanced**
> **Read time:** 15 min | **Level:** Intermediate

---

> HTTP tests prove that routes return responses and that the database changes correctly. Browser tests prove the thing the user actually touches: Livewire re-renders, buttons, file inputs, modals, and notification panels. Today we add Laravel Dusk and test FreelanceFlow in a real Chrome browser.

---

## What We Are Building Today

1. Install and configure Laravel Dusk
2. Add a Dusk base setup for workspace-aware tests
3. Add stable `dusk` selectors to interactive UI
4. Test client search and status filtering
5. Test client creation and validation
6. Test project file uploads
7. Test invoice PDF generation
8. Test the notification bell

---

## Step 1 - Install Laravel Dusk

```bash
composer require laravel/dusk --dev
php artisan dusk:install
php artisan dusk:chrome-driver --detect
```

Dusk creates:

- `tests/Browser/` - browser tests
- `tests/DuskTestCase.php` - the ChromeDriver base class
- `tests/Browser/screenshots/` - failure screenshots
- `tests/Browser/source/` - saved HTML on failures

Because Dusk runs a browser and PHPUnit as separate processes, do not use SQLite `:memory:`. Use a real SQLite file:

```bash
New-Item -ItemType File -Force database/testing.sqlite
```

Create `.env.dusk.local`:

```env
APP_NAME="FreelanceFlow Dusk"
APP_ENV=dusk
APP_KEY=base64:your-real-local-app-key
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
DB_DATABASE=C:\laragon\www\FreelanceFlow\database\testing.sqlite
DB_FOREIGN_KEYS=true

CACHE_STORE=array
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=array
BROADCAST_CONNECTION=log
LOG_CHANNEL="null"
DEBUGBAR_ENABLED=false
```

On Windows, `php artisan dusk` may print:

```text
Warning: TTY mode is not supported on Windows platform.
```

That warning is harmless. It only means PHPUnit cannot use interactive terminal mode in PowerShell.

---

## Step 2 - Add phpunit.dusk.xml

Create `phpunit.dusk.xml` so Dusk uses the browser test suite and the file-based SQLite database:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Browser">
            <directory>tests/Browser</directory>
        </testsuite>
    </testsuites>

    <php>
        <env name="APP_ENV" value="dusk"/>
        <env name="APP_URL" value="http://127.0.0.1:8000"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value="C:\laragon\www\FreelanceFlow\database\testing.sqlite"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="SESSION_DRIVER" value="file"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="BROADCAST_CONNECTION" value="log"/>
        <env name="LOG_CHANNEL" value="null" force="true"/>
        <env name="DEBUGBAR_ENABLED" value="false"/>
    </php>
</phpunit>
```

---

## Step 3 - Register Dusk and Workspace Helper Routes

In this project, Dusk v8 needs explicit registration in non-production environments. Add this near the top of `App\Providers\AppServiceProvider::boot()`:

```php
if (class_exists(\Laravel\Dusk\Dusk::class)) {
    \Laravel\Dusk\Dusk::register(['environments' => ['local', 'testing', 'dusk']]);
}
```

Then add a workspace helper route to `routes/web.php`:

```php
if (! app()->isProduction()) {
    Route::get('/testing/set-workspace/{workspace}', function (\App\Models\Workspace $workspace) {
        abort_unless(auth()->user()?->hasWorkspaceAccess($workspace), 403);

        session(['current_workspace_id' => $workspace->id]);

        return response('OK');
    })->middleware('auth')->name('testing.set-workspace');
}
```

This route keeps Dusk focused on the feature under test instead of forcing every browser test through the workspace creation flow.

After adding Dusk, refresh package and route discovery:

```bash
php artisan package:discover
php artisan route:clear
```

---

## Step 4 - Add Stable Dusk Selectors

Add `dusk` attributes to the controls the browser tests touch. These selectors are more stable than CSS classes.

Login form:

```blade
<flux:input wire:model="email" type="email" dusk="email-input" />
<flux:input wire:model="password" type="password" dusk="password-input" />
<flux:button wire:click="login" dusk="login-submit">Sign in</flux:button>
```

Client list:

```blade
<input wire:model.live.debounce.300ms="search" dusk="client-search" />

<button wire:click="clearSearch" dusk="clear-client-search" aria-label="Clear search">
    ...
</button>

<button wire:click="setStatus('{{ $value }}')" dusk="client-status-{{ $value ?: 'all' }}">
    {{ $label }}
</button>
```

Create client form:

```blade
<flux:input wire:model.live="name" dusk="client-name" />
<flux:input wire:model.live="email" dusk="client-email" />
<flux:input wire:model="phone" dusk="client-phone" />
<flux:input wire:model="company" dusk="client-company" />
<flux:select wire:model="status" dusk="client-status">...</flux:select>
<flux:button wire:click="save" dusk="save-client">Save client</flux:button>
```

Project file upload:

```blade
<input type="file" wire:model="newFile" dusk="project-file" />

<flux:button wire:click="uploadFile" dusk="upload-project-file">
    Upload
</flux:button>
```

Notification bell:

```blade
<button wire:click="toggleOpen" dusk="notification-bell" aria-label="Notifications">
    ...
</button>

<span dusk="notification-count">{{ $unreadCount }}</span>
```

Invoice PDF actions:

```blade
<button wire:click="confirmGenerate({{ $invoice->id }})"
        dusk="generate-invoice-pdf-{{ $invoice->id }}">
    ...
</button>

<a href="{{ route('invoices.download', $invoice) }}"
   dusk="download-invoice-pdf-{{ $invoice->id }}">
    ...
</a>

<flux:button wire:click="generatePdf" dusk="confirm-generate-invoice-pdf">
    Generate
</flux:button>
```

---

## Step 5 - Dusk Workspace Trait

Create `tests/Browser/DuskWithWorkspace.php`:

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait DuskWithWorkspace
{
    protected User $user;
    protected Workspace $workspace;

    protected function setUpWorkspace(string $role = 'admin'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'view clients', 'create clients', 'edit clients', 'delete clients',
            'view projects', 'create projects', 'edit projects', 'delete projects',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
            'send invoices', 'view reports', 'manage settings', 'manage users',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        foreach (['manager', 'freelancer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create(['password' => bcrypt('password')]);
        $this->user->assignRole($role);

        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->users()->attach($this->user->id, ['role' => 'owner']);
    }

    protected function loginWith(Browser $browser): void
    {
        $browser->driver->manage()->deleteAllCookies();

        $browser->loginAs($this->user)
            ->visit("/testing/set-workspace/{$this->workspace->id}")
            ->waitForText('OK');
    }
}
```

We use `loginAs()` because these tests are about client search, forms, uploads, invoices, and notifications. Testing the login form itself belongs in a separate, focused browser test.

---

## Step 6 - Use DatabaseTruncation, Not DatabaseMigrations

For Dusk with SQLite, prefer:

```php
use Illuminate\Foundation\Testing\DatabaseTruncation;

class ClientSearchTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;
}
```

`DatabaseMigrations` rolls migrations down between tests. Some migrations drop named foreign keys, which SQLite does not support. `DatabaseTruncation` migrates once and truncates rows between tests.

---

## Step 7 - Client Search Test

```php
public function test_live_search_filters_clients_as_user_types(): void
{
    Client::factory()->create([
        'name' => 'Acme Corporation',
        'company' => null,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    Client::factory()->create([
        'name' => 'Beta Industries',
        'company' => null,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    Client::factory()->create([
        'name' => 'Acme Consulting',
        'company' => null,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->browse(function (Browser $browser) {
        $this->loginWith($browser);

        $browser->visit('/clients')
            ->waitFor('@client-search')
            ->type('@client-search', 'Acme')
            ->waitForText('Acme Corporation')
            ->waitUntilMissingText('Beta Industries')
            ->assertSee('Acme Consulting')
            ->assertDontSee('Beta Industries');
    });
}
```

The important detail is `waitUntilMissingText()`. Livewire search is debounced, so an immediate `assertDontSee()` can run before the DOM updates.

---

## Step 8 - Create Client Test

```php
public function test_user_can_create_a_client_via_the_form(): void
{
    $this->browse(function (Browser $browser) {
        $this->loginWith($browser);

        $browser->visit('/clients/create')
            ->waitFor('@client-name')
            ->type('@client-name', 'New Test Client')
            ->type('@client-email', 'newtestclient@example.com')
            ->type('@client-phone', '+91 98765 43210')
            ->type('@client-company', 'Test Corp')
            ->select('@client-status', 'active')
            ->click('@save-client')
            ->waitForLocation('/clients')
            ->waitForText('Client added successfully.')
            ->assertSee('New Test Client');
    });
}
```

Validation tests should assert the real messages from the Livewire component:

```php
$browser->visit('/clients/create')
    ->waitFor('@save-client')
    ->click('@save-client')
    ->waitForText('The name is required')
    ->assertSee('The email is required');
```

---

## Step 9 - File Upload Test

Livewire file uploads need a real file on disk. Make the fixture look like the MIME type you are testing:

```php
$tempFile = tempnam(sys_get_temp_dir(), 'dusk_test_') . '.pdf';
file_put_contents($tempFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
```

Then test the browser flow:

```php
$browser->visit("/projects/{$project->id}/edit")
    ->waitFor('@project-file')
    ->attach('@project-file', $tempFile)
    ->waitForText('Ready to upload')
    ->assertSee(basename($tempFile))
    ->click('@upload-project-file')
    ->waitForText('Download')
    ->assertSee('Download');
```

We assert the new attachment row instead of the flash message because the flash component is outside the Livewire component being updated.

This test also exposed a real bug in the upload action. Capture metadata before storing the temp file:

```php
$originalName = $this->newFile->getClientOriginalName();
$mimeType = $this->newFile->getMimeType();
$size = $this->newFile->getSize();

$disk = config('freelanceflow.uploads.disk', 'local');
$storedName = $this->newFile->store('attachments', $disk);

$this->project->attachments()->create([
    'original_name' => $originalName,
    'stored_name' => $storedName,
    'mime_type' => $mimeType,
    'size' => $size,
    'disk' => $disk,
]);
```

---

## Step 10 - Invoice PDF Test

The invoice row changes after PDF generation: the generate button disappears and the download link appears.

```php
public function test_user_can_generate_an_invoice_pdf(): void
{
    $client = Client::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $invoice = Invoice::factory()->draft()->create([
        'number' => 'INV-DUSK-001',
        'client_id' => $client->id,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->browse(function (Browser $browser) use ($invoice) {
        $this->loginWith($browser);

        $browser->visit('/invoices')
            ->waitForText('INV-DUSK-001')
            ->click("@generate-invoice-pdf-{$invoice->id}")
            ->waitForText('Generate PDF?')
            ->click('@confirm-generate-invoice-pdf')
            ->waitFor("@download-invoice-pdf-{$invoice->id}")
            ->assertVisible("@download-invoice-pdf-{$invoice->id}");
    });
}
```

---

## Step 11 - Notification Bell Test

Create a database notification, visit the dashboard, and assert against the bell:

```php
$this->user->notify(new ProjectStatusChanged($project, 'active'));

$browser->visit('/dashboard')
    ->waitFor('@notification-bell')
    ->assertSeeIn('@notification-bell', '1');
```

When the panel opens, the component marks unread notifications as read. Test the exact badge element:

```php
$browser->visit('/dashboard')
    ->waitFor('@notification-bell')
    ->assertSeeIn('@notification-bell', '1')
    ->click('@notification-bell')
    ->waitUntilMissing('@notification-count')
    ->assertMissing('@notification-count');
```

Avoid `waitUntilMissingText('1')`; that is too broad because other page content can contain the same character.

---

## Step 12 - Run the Tests

Start the app server:

```bash
php artisan serve --env=dusk.local --host=127.0.0.1 --port=8000
```

Run all browser tests:

```bash
php artisan dusk
```

Run a single browser test file:

```bash
php artisan dusk tests/Browser/ClientSearchTest.php
```

Run a single method:

```bash
php artisan dusk --filter test_live_search_filters_clients_as_user_types
```

Verified result for today's browser suite:

```text
Tests: 12 passed (18 assertions)
```

The normal PHPUnit suite is still green too:

```text
Tests: 42 passed (109 assertions)
```

---

## Dusk Debugging Checklist

- If clicks hit Debugbar, set `DEBUGBAR_ENABLED=false` in `.env.dusk.local`
- If `Route [dusk.login] not defined`, run `php artisan package:discover` and register Dusk in non-production
- If `/testing/set-workspace` is missing, run `php artisan route:clear`
- If SQLite complains about dropping foreign keys, use `DatabaseTruncation` instead of `DatabaseMigrations`
- If `clear()` does not update Livewire, use the app's clear button or dispatch a real input event
- If upload validation fails, make the test file content match the extension/MIME
- If a Livewire action succeeds but the layout flash does not appear, assert the component's changed UI state instead
- If Dusk fails, inspect `tests/Browser/screenshots/` and `tests/Browser/source/`

---

## What We Learned Today

- Dusk tests real browser behavior, not just HTTP responses
- Browser tests need a persistent database, not SQLite `:memory:`
- Workspace-aware apps need explicit test setup for the browser session
- Stable `dusk` selectors make tests resilient to CSS changes
- Livewire tests should wait for DOM changes, not sleep blindly
- Browser tests are excellent at finding real integration bugs, like reading upload metadata after moving the temp file

---

## Day 44 - Full-Text Search with Scout

Tomorrow we add powerful full-text search to FreelanceFlow. We will install Laravel Scout, index clients and projects, add a global search bar, and highlight results so users can find anything quickly.

See you on Day 44.
