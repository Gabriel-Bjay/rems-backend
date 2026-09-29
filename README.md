# REMS — Rental & Estate Management System (Backend)

A REST API for managing rental properties end-to-end: property and unit listings, tenancies, billing, payments, maintenance, and agent commissions. Built with Laravel and PostgreSQL, this is the backend for [rems-frontend](https://github.com/Gabriel-Bjay/rems-frontend).

## What it does

- **Property & unit management** — properties, units, and per-unit recurring charges
- **Tenancy lifecycle** — create, activate, and end tenancies, with tenancy-specific charges and security deposits. Activating a tenancy opens the deposit and issues the first invoice
- **Automatic billing** — one invoice per billing period (monthly, quarterly or annual, anchored to the lease start date), with rent plus unit and tenancy charges, due five days after issue
- **Payments** — tenants report payments and staff confirm them; confirmed money is applied to the oldest unpaid invoices first, and any excess is kept as credit
- **Arrears** — invoices move to overdue after the due date, and the tenant is notified
- **Agent commissions** — recorded automatically on each confirmed payment, at the managing agent's rate
- **Maintenance** — tenants raise tickets, agents pick them up and resolve them with a repair cost, and the tenant is notified at each step
- **Dashboard** — one endpoint with occupancy, collections, a six-month billed vs collected trend, arrears, expiring leases, and recent activity
- **Listings** — public listing publish/approve/take-down workflow, separate from internal unit records
- **Notifications** — per-user notifications with mark-as-read
- **Role-based access** — Sanctum token auth. Admins see everything; owners see their own properties; agents see the properties and units they manage; tenants see only their own tenancy, invoices, payments and tickets

## Tech stack

- **Framework:** Laravel 13 (PHP 8.3)
- **Database:** PostgreSQL
- **Auth:** Laravel Sanctum (token-based)
- **Testing:** PHPUnit

## API overview

All endpoints below sit under `/api` and (aside from `/login`) require a Sanctum bearer token.

```
POST   /login
GET    /demo-accounts                     (public; empty unless the demo is on)
POST   /demo-login                        (public; sign in as a demo owner, agent or tenant)
POST   /logout
GET    /me
GET    /dashboard                         (figures scoped to the caller's role)
POST   /register                          (admin only)

/owners, /agents, /tenants                standard CRUD
/properties, /units, /unit-charges        standard CRUD
/tenancies, /tenancy-charges              standard CRUD
POST   /tenancies/{id}/activate
POST   /tenancies/{id}/end

/deposits, /vacate-notices                standard CRUD
/invoices, /invoice-items                 standard CRUD
POST   /invoices/generate                 (bill every active tenancy for its current period)
POST   /invoices/{id}/void                (needs a reason; not allowed once paid against)
/payments, /payment-allocations, /refunds standard CRUD
POST   /payments/{id}/confirm             (applies the payment to the oldest unpaid invoices)

/commissions                              standard CRUD
/maintenance-tickets                      standard CRUD
POST   /maintenance-tickets/{id}/assign
POST   /maintenance-tickets/{id}/resolve

/listings                                 standard CRUD
POST   /listings/{id}/approve
POST   /listings/{id}/publish
POST   /listings/{id}/take-down

/notifications                            standard CRUD
POST   /notifications/{id}/mark-read
POST   /notifications/mark-all-read
```

## Getting started

```bash
git clone https://github.com/Gabriel-Bjay/rems-backend.git
cd rems-backend
composer install

cp .env.example .env
php artisan key:generate
# set DB_CONNECTION=pgsql and your database credentials in .env

php artisan migrate
php artisan db:seed          # roles, plus the admin account from ADMIN_EMAIL / ADMIN_PASSWORD
php artisan serve
```

### Demo data

To explore the app with a realistic portfolio, set `DEMO_PASSWORD` in `.env` and run:

```bash
php artisan db:seed --class=DemoSeeder
```

This adds five Nairobi properties with 36 units, 31 tenancies, a year of invoices and payments (including tenants in arrears), maintenance tickets and listings. It also creates three demo logins, all using `DEMO_PASSWORD`:

| Role | Email |
| --- | --- |
| Owner | `demo.owner@rems.test` |
| Agent | `demo.agent@rems.test` |
| Tenant | `demo.tenant@rems.test` |

The seeder runs once. Running it again on a database that already has the demo data does nothing.

With `DEMO_PASSWORD` set, the default `php artisan db:seed` loads the demo data too. The Docker image runs that on every start, so setting `DEMO_PASSWORD` on a deployment is enough to turn the demo on. It also enables one-click demo sign-in: `GET /demo-accounts` lists the demo logins and `POST /demo-login` with a `role` of `owner`, `agent` or `tenant` signs in as that account without its password. Admin is never offered. Leave `DEMO_PASSWORD` empty on a deployment that holds real data.

### Scheduled billing

Two commands keep invoices current:

- `php artisan billing:generate-invoices` gives each active tenancy an invoice for its current billing period, if it doesn't have one yet. It runs daily at 06:00.
- `php artisan billing:mark-overdue` flags unpaid invoices past their due date. It runs daily at 06:30.

Run `php artisan schedule:work` locally, or add a cron entry for `php artisan schedule:run` in production. On hosts without a scheduler, the invoice list and the dashboard also mark overdue invoices when they are loaded.

### Tests

```bash
php artisan test
```

The suite runs against in-memory SQLite, so it needs no database setup.

## Related

- [rems-frontend](https://github.com/Gabriel-Bjay/rems-frontend) — Angular client for this API

## License

MIT
