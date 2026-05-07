# Day 11 — Seeders & Factories — Stop Adding Data by Hand

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 14 min · **Level:** Beginner to Intermediate

---

> *"Every day so far we have been adding clients manually through the form or Tinker. That works for one or two records. It does not work when you need 50 realistic clients to test pagination, search, or a dashboard chart. Today we write factories and seeders — and never manually create test data again."*

---

## Where We Are

FreelanceFlow has a complete client management system — create, read, update, soft delete, validation, notifications, and a component system. What we do not have is realistic data to work with.

Testing pagination with 3 clients is meaningless. Testing a status filter with handpicked records misses edge cases. Testing a dashboard with real numbers requires real volume.

Factories and seeders solve this permanently.

---

## What We Are Building Today

1. A **ClientFactory** using Faker — generates realistic client records
2. A **DatabaseSeeder** that seeds 50 clients in one command
3. **State methods** on the factory — seed only active clients, only leads, etc.
4. **Relationships in factories** — preparing for the projects table coming in Phase 2
5. A **fresh seed workflow** — wipe and reseed in one command during development

---

## The Difference Between Factories and Seeders

Beginners often confuse these. They have different jobs:

| | Factory | Seeder |
|---|---|---|
| **What it does** | Defines how to generate one fake model instance | Calls factories to insert records into the database |
| **Where it lives** | `database/factories/` | `database/seeders/` |
| **Used in** | Tests, seeders, Tinker | `php artisan db:seed` |
| **Generates data?** | Yes — using Faker | No — delegates to factories |

Think of it this way: the factory is the recipe, the seeder is the chef who decides how many portions to make.

---

## Step 1 — Create the ClientFactory

Laravel may have already generated a stub when you ran `make:model Client`. Check if `database/factories/ClientFactory.php` exists. If not, create it:

```bash
php artisan make:factory ClientFactory --model=Client
```

Open `database/factories/ClientFactory.php` and define the fake data:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        // Pick a consistent company name and derive the email from it
        $company = fake()->company();
        $domain  = str(fake()->domainWord())->slug() . '.com';

        return [
            'name'    => fake()->name(),
            'email'   => fake()->unique()->safeEmail(),
            'phone'   => fake()->boolean(70)   // 70% of clients have a phone
                            ? fake()->phoneNumber()
                            : null,
            'company' => fake()->boolean(80)   // 80% belong to a company
                            ? $company
                            : null,
            'notes'   => fake()->boolean(40)   // 40% have notes
                            ? fake()->sentences(2, true)
                            : null,
            'status'  => fake()->randomElement(['active', 'active', 'active', 'inactive', 'lead']),
        ];
    }

    // --- State methods ---

    // Only active clients
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    // Only inactive clients
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    // Only leads — no company, always has a phone
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'lead',
            'company' => null,
            'phone'   => fake()->phoneNumber(),
        ]);
    }

    // Client with no optional fields — bare minimum record
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone'   => null,
            'company' => null,
            'notes'   => null,
        ]);
    }
}
```

**What to notice:**

- `fake()->unique()->safeEmail()` — `unique()` ensures no two generated records share the same email. `safeEmail()` generates addresses in safe domains like `example.com` so test emails never accidentally reach a real inbox
- The `status` array has `'active'` three times — this is a simple way to weight the distribution. Most real clients are active, fewer are leads or inactive
- `fake()->boolean(70)` returns `true` 70% of the time — used to make optional fields realistically sparse
- State methods return `$this->state(...)` — they merge overrides on top of `definition()`. You chain them onto the factory call

---

## Step 2 — Make Sure the Model Uses HasFactory

Open `app/Models/Client.php` and confirm `HasFactory` is included:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'notes',
        'status',
    ];
}
```

`HasFactory` links the `Client` model to `ClientFactory` automatically by convention — no configuration needed.

---

## Step 3 — Build the DatabaseSeeder

Open `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create a demo user account so you can log in immediately after seeding
        User::factory()->create([
            'name'  => 'Demo User',
            'email' => 'demo@freelanceflow.test',
        ]);

        // 50 clients with realistic distribution:
        // 30 active, 10 inactive, 10 leads
        Client::factory()->count(30)->active()->create();
        Client::factory()->count(10)->inactive()->create();
        Client::factory()->count(10)->lead()->create();

        $this->command->info('✓ Seeded 50 clients (30 active, 10 inactive, 10 leads)');
    }
}
```

**Why seed a demo user?** After running `php artisan migrate:fresh --seed` in development, your database is wiped. Without seeding a user, you cannot log in to see the seeded clients. The demo account lets you go straight to the browser and log in with `demo@freelanceflow.test` — no registration step needed.

> The seeded user has no password set explicitly — add one using `'password' => bcrypt('password')` if you need to log in via the UI. Or use Tinker: `User::first()->update(['password' => bcrypt('password')]);`

---

## Step 4 — Run the Seeder

Seed the database without wiping it:

```bash
php artisan db:seed
```

Or wipe everything and reseed from scratch (your most-used command during development):

```bash
php artisan migrate:fresh --seed
```

`migrate:fresh` drops all tables, runs every migration from scratch, then calls the seeder. Your database is in a clean, predictable state with realistic data in under 10 seconds.

Check the result in Tinker:

```bash
php artisan tinker

Client::count();                          // 50
Client::where('status', 'active')->count(); // 30
Client::where('status', 'lead')->count();   // 10
Client::latest()->first()->name;            // a real-looking name
```

---

## Step 5 — Using Factories in Tinker

Factories are not just for seeders. You can call them directly in Tinker any time you need a quick record:

```bash
php artisan tinker

# Create one client
Client::factory()->create();

# Create 5 active clients
Client::factory()->count(5)->active()->create();

# Create a lead with specific overrides
Client::factory()->lead()->create([
    'name'  => 'Specific Test Client',
    'email' => 'test@example.com',
]);

# Make without saving (useful in tests)
Client::factory()->make();

# Make 10 without saving
Client::factory()->count(10)->make();
```

`make()` generates the model instance and fills its attributes without hitting the database. `create()` saves it. In tests, you often want `make()` for unit tests and `create()` for feature tests that need real records.

---

## Step 6 — Create a Dedicated ClientSeeder

For larger projects, it is cleaner to have one seeder class per model rather than putting everything in `DatabaseSeeder`. Create a dedicated seeder:

```bash
php artisan make:seeder ClientSeeder
```

Open `database/seeders/ClientSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe existing clients before seeding
        Client::truncate();

        Client::factory()->count(30)->active()->create();
        Client::factory()->count(10)->inactive()->create();
        Client::factory()->count(10)->lead()->create();

        $this->command->info('✓ Seeded 50 clients');
    }
}
```

Call it from `DatabaseSeeder`:

```php
public function run(): void
{
    User::factory()->create([
        'name'  => 'Demo User',
        'email' => 'demo@freelanceflow.test',
    ]);

    $this->call([
        ClientSeeder::class,
        // ProjectSeeder::class,   ← we add these in Phase 2
        // InvoiceSeeder::class,
    ]);
}
```

Now you can also run individual seeders without re-running everything:

```bash
# Run only the client seeder
php artisan db:seed --class=ClientSeeder
```

---

## Step 7 — Faker Reference for FreelanceFlow

A quick reference of the Faker methods most useful for FreelanceFlow data:

```php
// Names and people
fake()->name()              // "Dr. Jane Smith"
fake()->firstName()         // "Jane"
fake()->lastName()          // "Smith"
fake()->jobTitle()          // "Senior Developer"

// Companies and business
fake()->company()           // "Acme Corp"
fake()->companySuffix()     // "Ltd"
fake()->domainWord()        // "acme"
fake()->domainName()        // "acme.com"

// Contact
fake()->email()             // "jane@example.net" (might be real domain)
fake()->safeEmail()         // "jane@example.com" (always safe)
fake()->unique()->safeEmail() // guaranteed unique
fake()->phoneNumber()       // "+1-555-867-5309"

// Text
fake()->word()              // "voluptatem"
fake()->sentence()          // "The quick brown fox."
fake()->sentences(3, true)  // 3 sentences as one string
fake()->paragraph()         // a full paragraph
fake()->text(200)           // random text up to 200 chars

// Numbers and booleans
fake()->boolean()           // true or false (50/50)
fake()->boolean(70)         // true 70% of the time
fake()->numberBetween(1, 100) // integer in range
fake()->randomFloat(2, 10, 999) // float with 2 decimal places

// Dates
fake()->dateTimeBetween('-1 year', 'now') // DateTime object
fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d')

// Random selection
fake()->randomElement(['active', 'inactive', 'lead'])
fake()->randomElements(['a', 'b', 'c', 'd'], 2) // pick 2
```

---

## The Development Workflow from Here

From Day 12 onwards your development loop for FreelanceFlow looks like this:

```bash
# Start of a new feature — fresh database with realistic data
php artisan migrate:fresh --seed

# Run the dev server
npm run dev &
php artisan serve

# Need more test data mid-session
php artisan db:seed --class=ClientSeeder

# Quick one-off record in Tinker
php artisan tinker
>>> Client::factory()->lead()->create(['name' => 'Test Lead'])
```

You will never click through a form to create test data again.

---

## What We Learned Today

- **Factory vs Seeder** — factory defines the recipe, seeder decides how many portions to cook
- **`fake()->unique()->safeEmail()`** — guarantees unique emails that never reach a real inbox
- **Weighted randomness** — repeat values in `randomElement()` arrays to bias the distribution naturally
- **`fake()->boolean(70)`** — make optional fields realistically sparse, not always filled
- **State methods** — `.active()`, `.inactive()`, `.lead()` for controlled distributions
- **`HasFactory` trait** — links the model to its factory by naming convention, no config needed
- **`make()` vs `create()`** — `make()` builds without saving, `create()` saves to database
- **`migrate:fresh --seed`** — your most-used development command from here onwards
- **Dedicated seeder classes** — one seeder per model, called from `DatabaseSeeder` with `$this->call()`

FreelanceFlow now has 50 realistic clients to work with. Pagination, search, and filters will actually mean something from here.

---

## Day 12 — Query Scopes & Accessors

Tomorrow we make Eloquent smarter. Right now the client list returns `Client::latest()->get()` — everything, always. We will add local query scopes to filter by status directly on the model, model accessors to format data before it reaches the view, and mutators to clean data before it hits the database.

See you on Day 12.