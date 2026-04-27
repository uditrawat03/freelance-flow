# Day 2 — Routes & Controllers — The Front Door of Laravel

> **Series:** Laravel Zero to Hero · **Phase 1 — Foundations** · April 27, 2026
> **Read time:** 10 min · **Level:** Beginner

---

> *"Every single thing a user does in your app — clicking a link, submitting a form, calling your API — starts in one file: routes/web.php. Master this file, and you master how Laravel thinks."*

---

## Recap & Context

Yesterday we created FreelanceFlow from scratch, connected our database, and ran our first migrations. Today we build the first real functionality — the URL structure of our app. This is Day 2 of our 90-day Laravel Zero to Hero series.

When someone visits `freelanceflow.com/clients`, how does Laravel know what to show them? That's exactly what we're answering today.

---

## What is a Route?

A route is a mapping between a URL and a piece of code. When a browser visits a URL, Laravel checks its route list, finds the match, and runs the associated code.

Open `routes/web.php` right now. You'll see one route already there:

```php
// routes/web.php
Route::get('/', function () {
    return view('welcome');
});
```

This says: when someone makes a GET request to `/`, run this function and return the welcome view. That's the entire Laravel welcome page — one route, one view.

Let's add our own. Add this below the existing route:

```php
Route::get('/hello', function () {
    return 'Hello FreelanceFlow!';
});
```

Visit `http://localhost:8000/hello` — your first custom route is live.

---

## Route Parameters

What if we want a different page for each client — like `/clients/1`, `/clients/2`? We don't write one route per client. We use route parameters:

```php
// {id} captures whatever is in that URL position
Route::get('/clients/{id}', function ($id) {
    return 'Client ID: ' . $id;
});
```

Laravel captures whatever is in that position of the URL and passes it to your function as a variable. Visit `/clients/1`, then `/clients/42` — the variable changes each time. This is how every show page in FreelanceFlow will work.

---

## The Full Client Route Set

For FreelanceFlow's client management, we need these 7 routes:

| Method | URL | Purpose |
|---|---|---|
| `GET` | `/clients` | List all clients |
| `GET` | `/clients/create` | Show the create form |
| `POST` | `/clients` | Save a new client |
| `GET` | `/clients/{id}` | View one client |
| `GET` | `/clients/{id}/edit` | Show the edit form |
| `PUT` | `/clients/{id}` | Update a client |
| `DELETE` | `/clients/{id}` | Delete a client |

Writing all of these manually every time would be tedious. Laravel has a better way.

---

## Resource Routes — 7 Routes in One Line

A **resource route** generates all 7 standard CRUD routes automatically:

```php
// routes/web.php — the entire client route set in one line
Route::resource('clients', ClientController::class);
```

Run `php artisan route:list` after adding it and you'll see all 7 routes appear instantly, with correct HTTP verbs and named routes:

```
GET    /clients                clients.index
GET    /clients/create         clients.create
POST   /clients                clients.store
GET    /clients/{client}       clients.show
GET    /clients/{client}/edit  clients.edit
PUT    /clients/{client}       clients.update
DELETE /clients/{client}       clients.destroy
```

This is why people love Laravel — it respects your time.

---

## What is a Controller?

A Controller is a PHP class that groups all the logic for a related set of actions. Instead of writing code directly inside the route file, you move it into a Controller method. This keeps your code organised and testable as the app grows.

Think back to our restaurant analogy from Day 1. The Controller is the waiter. Each method on the Controller corresponds to one action:

| Method | Route | What it does |
|---|---|---|
| `index()` | `GET /clients` | List all clients |
| `create()` | `GET /clients/create` | Show create form |
| `store()` | `POST /clients` | Save new client |
| `show()` | `GET /clients/{id}` | View one client |
| `edit()` | `GET /clients/{id}/edit` | Show edit form |
| `update()` | `PUT /clients/{id}` | Save changes |
| `destroy()` | `DELETE /clients/{id}` | Delete client |

Generate the controller with Artisan — it pre-stubs all 7 methods for you:

```bash
php artisan make:controller ClientController --resource
```

Open `app/Http/Controllers/ClientController.php`. You'll see all 7 empty methods already waiting. Today we build out `index()`.

---

## Building the Client List — FreelanceFlow's First Real Page

Let's wire up the `index()` method, pass some dummy data to a Blade view, and render the first real FreelanceFlow screen.

In [`ClientController.php`](../app/Http/Controllers/ClientController.php), fill in the `index()` method:


```php
// app/Http/Controllers/ClientController.php
class ClientController extends Controller
{
    public function index()
    {
        $clients = [
            ['name' => 'Acme Corp',         'email' => 'hello@acme.com'],
            ['name' => 'Stark Industries',  'email' => 'tony@stark.com'],
            ['name' => 'Wayne Enterprises', 'email' => 'bruce@wayne.com'],
        ];

        return view('clients.index', compact('clients'));
    }
}
```

`compact('clients')` is a PHP shortcut that passes the `$clients` variable to the view under the key `'clients'`. Now create the view file at [`resources/views/clients/index.blade.php`](../resources/views/clients/index.blade.php):

```blade
{{-- resources/views/clients/index.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <h1>FreelanceFlow — Clients</h1>

    @foreach ($clients as $client)
        <div>
            <strong>{{ $client['name'] }}</strong>
            — {{ $client['email'] }}
        </div>
    @endforeach

</body>
</html>
```

Visit `http://localhost:8000/clients` — you'll see your first FreelanceFlow screen. The client list is alive.

> **Note:** We're using a hardcoded array for now. On Day 5 we introduce Eloquent and replace this with real database queries.

---

## Commands for Today

```bash
# Generate a resource controller with all 7 methods pre-stubbed
php artisan make:controller ClientController --resource

# List all registered routes
php artisan route:list

# Filter route list by name
php artisan route:list --name=client

# Start the dev server if it's not already running
php artisan serve
```

---

## Resource Route Reference

```
Route::resource('clients', ClientController::class) generates:

GET    /clients               → index()    list all clients
GET    /clients/create        → create()   show create form
POST   /clients               → store()    save new record
GET    /clients/{client}      → show()     view one record
GET    /clients/{client}/edit → edit()     show edit form
PUT    /clients/{client}      → update()   save changes
DELETE /clients/{client}      → destroy()  delete record
```

---

## What's Next

Tomorrow on **Day 3** we build the FreelanceFlow app shell using Blade layouts, `@extends`, and `@yield`. Right now the client list is bare HTML — tomorrow every page in the app gets a proper navbar, sidebar, and consistent layout by inheriting from one master template.