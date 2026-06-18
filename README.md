# FreelanceFlow

A freelance business management platform built publicly as part of the
**Laravel Zero to Hero** 90-day series.

FreelanceFlow helps freelancers manage clients, track projects, send invoices,
and collect payments, all in one place.

---

## Series

This repository is built live, one day at a time, as part of a public vlog series.

- **Phase 1 (Days 01-15):** Foundations: Laravel, Blade, Livewire, CRUD, auth
- **Phase 2 (Days 16-37):** Core features: relationships, queues, mail, invoicing, payments, multi-tenancy
- **Phase 3 (Days 38-54):** Advanced scale: Redis, Reverb, testing, security, Horizon, Telescope, Inertia, GraphQL, Octane
- **Phase 4 (Days 55-90):** Production and growth: servers, CI/CD, deployments, monitoring, backups, multi-server scaling

Follow along: [your blog / LinkedIn / YouTube link]

---

## Current Status

**Day 55 complete - production server setup in progress**

- Client management: create, read, update, soft delete
- Authentication: login, register, password reset (Livewire + Flux UI)
- Live search and pagination
- Form validation with custom rules
- Blade component system
- Realistic seed data
- Multi-tenant workspaces, roles, policies, invoices, Stripe payments, queues, notifications, Redis caching, Reverb, Horizon, Telescope, 2FA, localization, Inertia, GraphQL, and Octane/FrankenPHP readiness

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13.x, PHP 8.3 |
| Frontend | Livewire 4, Flux UI, Tailwind CSS 4 |
| Database | MySQL 8 |
| Assets | Vite |

---

## Getting Started

```bash
git clone https://github.com/yourusername/freelance-flow.git
cd freelance-flow

composer install
npm install

cp .env.example .env
php artisan key:generate

# Configure your database in .env, then:
php artisan migrate:fresh --seed

npm run dev
php artisan serve
```

Login with the seeded demo account:

- **Email:** demo@freelanceflow.test
- **Password:** password

---

## License

MIT
