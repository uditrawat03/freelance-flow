# Day 1 — Why Laravel? Setting up FreelanceFlow from scratch

> **Series:** Laravel Zero to Hero · **Phase 1 — Foundations** · April 27, 2026
> **Read time:** 8 min · **Level:** Beginner

---

> *"By day 90 of this series, you'll have built a production-ready SaaS that handles thousands of users, runs on Kubernetes, and processes real payments. No course, no fake data — a real, deployable application. Today we write line one."*

---

## Introduction

Welcome to **Laravel Zero to Hero** — a 90-day series where we build a real, production-grade application from the very first `composer create-project` command all the way to a highly scalable system design that would survive a Product Hunt launch.

The app we're building is called **FreelanceFlow** — a client and project management platform for freelancers. Think: manage clients, track projects, send invoices, collect payments, and see your revenue at a glance. Every developer has wished this app existed. By the end of this series, you'll have built it yourself.

But first — why Laravel?

---

## Why Laravel?

Laravel was created by Taylor Otwell in 2011 with one core idea: web development should be enjoyable. If you've worked with raw PHP or older frameworks, you know how painful the experience can be. Laravel changed that completely.

Today, Laravel is the most starred PHP framework on GitHub, powers companies like Invoice Ninja, Fathom Analytics, and hundreds of SaaS products you use daily. It has a massive ecosystem — queues, real-time broadcasting, payment processing, search, testing tools — all first-party and beautifully documented.

Here's what makes it special for building products like FreelanceFlow:

| Pillar | What it means |
|---|---|
| **Developer happiness** | Expressive syntax that reads like English. No boilerplate, no XML config files. |
| **MVC architecture** | Model → View → Controller. A clean separation that keeps code organised as apps grow. |
| **First-party ecosystem** | Eloquent ORM, Blade templates, Artisan CLI, Sanctum, Horizon, Telescope — all official. |

---

## Understanding MVC in 60 Seconds

MVC stands for **Model–View–Controller**. It's a way of organising your code so that data, presentation, and logic stay separate from each other.

Think of it like a restaurant:

- 🍳 **Model** = the kitchen — handles all the data (reading from the database, saving records)
- 🪑 **View** = the dining room — what the customer sees (your HTML pages)
- 🧑‍💼 **Controller** = the waiter — takes the customer's request, asks the kitchen for data, brings it to the dining room

In FreelanceFlow, when a user visits `/clients`:

1. The **Controller** receives the request
2. Asks the **Client Model** for all records
3. Passes them to a **Blade View** which renders the HTML table

Simple, clean, testable.

---

## Installing Laravel & Creating FreelanceFlow

You need PHP 8.2+, Composer, and a database (MySQL or SQLite). Once those are ready, this is all it takes:

```bash
# Install the Laravel installer globally
composer global require laravel/installer

# Create our project
laravel new freelance-flow

# Enter the project
cd freelance-flow

# Start the development server
php artisan serve
```

Open your browser to `http://localhost:8000` — you should see the Laravel welcome page. That's FreelanceFlow's first heartbeat.

---

## Project Structure Tour

Open the project in your editor. Here's a quick tour of the folder structure you'll live in every day:

```
freelance-flow/
├── app/
│   ├── Http/
│   │   ├── Controllers/   ← request handlers
│   └── Models/            ← database models (Eloquent)
├── database/
│   ├── migrations/        ← schema version control
│   └── seeders/           ← test data
├── resources/
│   └── views/             ← Blade templates (HTML)
├── routes/
│   └── web.php            ← all your URL routes
└── .env                   ← database, mail, app config
```

---

## Configuring the .env File

Open `.env` and update these values to match your local environment:

```env
APP_NAME=FreelanceFlow
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freelance_flow
DB_USERNAME=root
DB_PASSWORD=your_password
```

> **Note:** Never commit `.env` to Git. Laravel ships with `.env.example` for this reason — commit that instead.

Then run the default migrations that ship with Laravel:

```bash
php artisan migrate
```

---

## Useful Commands for Today

```bash
# Check PHP version (needs 8.2+)
php -v

# Check Composer
composer -V

# Generate app encryption key (already done by installer)
php artisan key:generate

# List all available artisan commands
php artisan list

# See current app info
php artisan about

# View all routes (empty for now)
php artisan route:list
```

---

## SQLite Alternative — Zero Config

If you don't want to set up MySQL locally, SQLite works perfectly for development:

```env
# In .env, replace all DB_ lines with just this:
DB_CONNECTION=sqlite
```

```bash
# Create the SQLite file
touch database/database.sqlite

# Run migrations
php artisan migrate
```

---

## What's Next

Over the next 14 days of Phase 1 we'll build the entire foundation of FreelanceFlow: authentication, a client management CRUD, form validation, Blade layouts, and proper database design. By Day 15 you'll have a fully working app you can show people.

**Next (Day 2):** Routes and Controllers — the front door of every Laravel application. Every URL you visit, every API call you make — it all starts in `routes/web.php`.

**Follow me on [Linked](https://www.linkedin.com/in/udit-rawat-498b38111/)**: DM me for any quries.