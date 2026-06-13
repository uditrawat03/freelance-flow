# Day 41 - Feature Testing with PHPUnit

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3 - Advanced**
> **Read time:** 16 min | **Level:** Intermediate

---

> We have built 40 days of features. Now Phase 3 is where we prove those features with automated tests. Tests written after the fact are still valuable: they document behavior, prevent regressions, and give you confidence to refactor. Today we add the first real FreelanceFlow test suite.

---

## What We Are Building Today

1. Testing environment setup with SQLite in-memory and side-effect-free drivers
2. A reusable `WithWorkspace` trait for authenticated workspace tests
3. Livewire client feature tests for create, read, update, and delete
4. API endpoint tests authenticated with Sanctum
5. Mail, queue, notification, and event assertions
6. Invoice service/model tests
7. Database assertions with `assertDatabaseHas`, `assertSoftDeleted`, and workspace-scope checks

---

## Step 1 - Configure the Testing Environment

PHPUnit already uses the testing environment from `phpunit.xml`. FreelanceFlow uses an in-memory SQLite database and array/sync drivers for fast, isolated tests:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="BROADCAST_CONNECTION" value="null"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="LOG_CHANNEL" value="null"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

`BCRYPT_ROUNDS=4` keeps user factories fast. `LOG_CHANNEL=null` prevents test runs from trying to use production logging integrations such as Slack.

Run the suite with:

```bash
php artisan test
```

---

## Step 2 - Create a Workspace Test Helper

Most FreelanceFlow features need an authenticated user and a current workspace. Create `tests/Traits/WithWorkspace.php`:

```php
<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

trait WithWorkspace
{
    protected User $user;
    protected Workspace $workspace;

    protected function setUpWorkspace(string $role = 'admin'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'manager', 'freelancer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->workspace = Workspace::factory()->create([
            'owner_id' => $this->user->id,
        ]);

        $this->workspace->users()->attach($this->user->id, ['role' => 'owner']);

        $this->actingAs($this->user);
        session(['current_workspace_id' => $this->workspace->id]);
    }
}
```

This trait keeps every feature test focused on behavior instead of setup noise.

---

## Step 3 - Add Missing Factories

Create `database/factories/WorkspaceFactory.php` and `database/factories/InvoiceFactory.php`. The test suite uses these factories to create complete records without repeating boilerplate in each test.

The invoice factory should include realistic `line_items`, totals, and helper states such as `draft()`, `sent()`, `paid()`, and `overdue()`.

---

## Step 4 - Client Feature Tests

FreelanceFlow's web client create/edit screens are Livewire components, not classic `POST /clients` controller routes. That means web feature tests should use Livewire's test helper:

```php
Livewire::test(\App\Livewire\Clients\Create::class)
    ->set('name', 'Acme Corp')
    ->set('email', 'hello@acme.com')
    ->set('status', 'active')
    ->call('save')
    ->assertHasNoErrors()
    ->assertRedirect(route('clients.index'));
```

The Day 41 suite covers:

- authenticated users can view the client list
- guests are redirected to login
- users can create clients through Livewire
- validation catches missing name and email
- users can view clients in their workspace
- users cannot view clients from another workspace
- users can update clients through Livewire
- users can soft-delete clients through Livewire
- workspace global scope isolates records

Important fix: `Client`, `Project`, and `Invoice` now auto-fill `user_id` during creation when a user is authenticated. That keeps policies and ownership checks aligned with the workspace setup.

---

## Step 5 - API Feature Tests

API tests authenticate with Sanctum:

```php
protected function setUp(): void
{
    parent::setUp();

    $this->setUpWorkspace();
    Sanctum::actingAs($this->user, ['*']);
}
```

The API suite covers:

- listing clients
- filtering clients by status
- creating clients
- validation failure responses
- showing a client with projects
- updating clients
- deleting clients
- unauthenticated requests returning JSON 401
- another workspace's client returning JSON 404

Two production code fixes came from these tests:

- `ClientController@show` and `ClientController@update` now return `200 OK` instead of `201 Created`.
- API exception rendering now maps `NotFoundHttpException` to JSON 404, not JSON 500.

---

## Step 6 - Events, Queue, Mail, and Notifications

`Project` dispatches `ProjectCreated` through its model event. `ProjectService` no longer dispatches the same event a second time.

The project tests cover:

```php
Event::fake([\App\Events\ProjectCreated::class]);

$project = app(\App\Services\ProjectService::class)->create([...]);

Event::assertDispatched(\App\Events\ProjectCreated::class);
Event::assertDispatchedTimes(\App\Events\ProjectCreated::class, 1);
```

They also verify:

- the `ProjectCreated` listener dispatches `SendProjectNotification`
- the notification job sends `ProjectCreated` mail to the client
- changing a project status sends `ProjectStatusChanged` notification to the user

Use `Queue::fake()`, `Mail::fake()`, `Notification::fake()`, and `Event::fake()` to assert side effects without actually sending anything.

---

## Step 7 - Invoice Tests

Invoice tests cover service and model behavior:

- invoice numbers are generated as `INV-YYYY-001`
- totals are calculated from line items and tax rate
- `markAsSent()` updates status and `issued_at`
- `markAsPaid()` updates status and `paid_at`
- sent invoices past `due_at` are overdue
- paid invoices are not overdue even when the due date has passed

Example total assertion:

```php
$this->assertEquals(50000, $invoice->subtotal);
$this->assertEquals(9000, $invoice->tax_amount);
$this->assertEquals(59000, $invoice->total);
```

---

## Step 8 - Run the Tests

```bash
php artisan test
```

Current passing result:

```text
Tests: 30 passed (85 assertions)
Duration: about 5 seconds
```

Useful focused commands:

```bash
php artisan test tests/Feature/ClientTest.php
php artisan test tests/Feature/Api/ClientApiTest.php
php artisan test --filter test_can_create_a_client_via_api
```

---

## Database Assertion Reference

```php
$this->assertDatabaseHas('clients', [
    'email' => 'test@example.com',
    'workspace_id' => $this->workspace->id,
]);

$this->assertDatabaseMissing('clients', [
    'email' => 'missing@example.com',
]);

$this->assertSoftDeleted('clients', ['id' => $client->id]);
$this->assertNotSoftDeleted($client);
$this->assertModelMissing($client);
$this->assertDatabaseCount('clients', 5);
```

---

## What We Learned Today

- `RefreshDatabase` gives every test a clean database.
- `WithWorkspace` avoids repeating authentication, roles, workspace membership, and session setup.
- Livewire component tests are the right fit for FreelanceFlow's web client create/edit flows.
- `Sanctum::actingAs()` makes API authentication fast.
- `Event::fake()` and `assertDispatchedTimes()` can catch duplicate events.
- `Queue::fake()`, `Mail::fake()`, and `Notification::fake()` let us test side effects safely.
- API tests are excellent at exposing incorrect status codes and exception handling.
- Workspace isolation needs explicit tests because it is a core multi-tenancy guarantee.

---

## Day 42 - HTTP Tests and Mocking

Tomorrow we go deeper on HTTP testing. We will test the Stripe webhook handler, mock external HTTP calls with `Http::fake()`, test file uploads, write authentication flow tests, and build helpers for signed Stripe webhook payloads.
