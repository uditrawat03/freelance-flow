# Day 4 — Migrations & database — version control for your schema

> **Series:** Laravel Zero to Hero · **Phase 1 — Foundations** · April 30, 2026
> **Read time:** 11 min · **Level:** Beginner
---

> *"Most beginners create database tables by clicking around in phpMyAdmin. That's fine — until your teammate pulls your code and has no idea what tables to create. Laravel migrations solve this completely. Your database schema lives in code, versioned in Git, reproducible on any machine in one command."*

---

## Recap & Context

We have an app shell, we have routes, we have a controller — but FreelanceFlow's client list is still powered by a hardcoded array. Today we replace that with a real database table. By the end of this post you'll have a `clients` table with proper columns, created entirely through Laravel's migration system.

---

## What is a Migration?

A migration is a PHP file that describes a database change. It has two methods: `up()` which applies the change (create a table, add a column), and `down()` which reverses it (drop the table, remove the column). Laravel runs these files in order, tracking which ones have been executed.

Think of migrations as Git commits for your database. Every schema change is recorded, reversible, and shared with your whole team through version control. No more "oh you also need to add that column manually" messages in Slack.

---

## Designing the Clients Table

Before writing any code, think about what data a freelancer needs to track about a client:

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | Auto-incrementing primary key. Laravel adds this automatically. |
| `name` | string | Client or company name. Required. |
| `email` | string, unique | Primary contact email. Unique constraint prevents duplicates. |
| `phone` | string, nullable | Optional phone number. |
| `company` | string, nullable | Company name when the client is an individual at a firm. |
| `notes` | text, nullable | Free-form notes about the client relationship. |
| `status` | string, default `active` | Client status: active, inactive, or lead. |
| `timestamps` | created_at, updated_at | Automatically managed by Laravel. Always include these. |

---

## Step 1 — Creating the Migration

Laravel's `make:migration` command creates a timestamped migration file. The naming convention matters — use `create_tablename_table` and Laravel automatically scaffolds the `Schema::create` call for you:

```bash
php artisan make:migration create_clients_table
```

This creates a file in `database/migrations/` with a timestamp prefix like [`2026_04_29_000000_create_clients_table.php`](../database/migrations/2026_04_29_000000_create_clients_table.php). The timestamp controls execution order — migrations always run oldest first.

---

## Step 2 — Writing the Migration

Open the generated file and fill in the `up()` method. The Schema builder uses a fluent, readable API — each line is one column:

```php
// database/migrations/xxxx_xx_xx_create_clients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();                                    // bigint, primary key, auto-increment
            $table->string('name');                          // varchar(255), required
            $table->string('email')->unique();               // unique index on email
            $table->string('phone')->nullable();             // optional
            $table->string('company')->nullable();           // optional
            $table->text('notes')->nullable();               // long text, optional
            $table->string('status')->default('active');     // defaults to 'active'
            $table->timestamps();                            // created_at + updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
```

---

## Step 3 — Running the Migration

One command creates the table in your database:

```bash
php artisan migrate
```

Laravel checks the `migrations` table (created on your very first `migrate` run) to know which files have already been executed. It only runs new ones — so running `migrate` again is always safe.

Open your database GUI (TablePlus, DBngin, or similar) and refresh. The `clients` table is there with all eight columns, exactly as designed.

---

## The Rollback System

Made a mistake? The `down()` method is your escape hatch. Rolling back drops the table and resets the migration record so you can edit the file and re-run it cleanly:

```bash
# Undo the last batch of migrations
php artisan migrate:rollback

# Rollback everything and re-run from scratch
php artisan migrate:fresh

# See the status of all migrations
php artisan migrate:status
```

> **Warning:** `migrate:fresh` drops all tables and re-runs every migration. Use it freely in development when you want a clean slate. Never run it on production — it destroys all data.

---

## Adding a Column Later — The Right Way

Never edit an existing migration file after it has been run. That breaks the migration history for everyone on the project. Instead, always create a new migration for every schema change:

```bash
# Create a new migration for every change
php artisan make:migration add_avatar_to_clients_table
```

```php
// Inside the new migration's up() method:
Schema::table('clients', function (Blueprint $table) {
    $table->string('avatar')->nullable()->after('name');
});
```

This is the golden rule of migrations: **one change, one file, always forward**.

---

## Blueprint Column Type Reference

```php
// Common column types
$table->id();                           // bigint unsigned, PK, auto-increment
$table->string('name');                 // varchar(255)
$table->string('name', 100);           // varchar(100)
$table->text('bio');                    // text
$table->integer('age');                 // int
$table->decimal('amount', 10, 2);      // decimal(10,2) — ideal for money
$table->boolean('is_active');           // tinyint(1)
$table->date('due_date');              // date
$table->timestamp('sent_at');          // timestamp
$table->timestamps();                   // created_at + updated_at
$table->softDeletes();                  // deleted_at (for soft delete)
$table->foreignId('user_id')->constrained(); // FK with constraint

// Column modifiers — chain these
->nullable()            // allow NULL
->default('active')     // set default value
->unique()              // unique index
->after('name')         // column position (MySQL only)
->unsigned()            // no negative numbers
```

---

## Migration Command Reference

```bash
# Create a new migration file
php artisan make:migration create_clients_table

# Run all pending migrations
php artisan migrate

# Check which migrations have run
php artisan migrate:status

# Undo the last batch
php artisan migrate:rollback

# Drop all tables and re-run from scratch (dev only)
php artisan migrate:fresh

# Fresh migration + run all seeders
php artisan migrate:fresh --seed
```

---

## What's Next

The `clients` table exists in the database — but we still need a way to talk to it from PHP. Tomorrow on **Day 5** we introduce Eloquent ORM, Laravel's ActiveRecord implementation. We'll create a `Client` model, replace the hardcoded array in the controller with a real database query, and see how Eloquent turns a table row into a PHP object with zero boilerplate.