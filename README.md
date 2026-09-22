# REMS — Rental & Estate Management System (Backend)

A REST API for managing rental properties end-to-end: property and unit listings, tenancies, billing, payments, maintenance, and agent commissions. Built with Laravel and PostgreSQL, this is the backend for [rems-frontend](https://github.com/Gabriel-Bjay/rems-frontend).

## What it does

- **Property & unit management** — properties, units, and per-unit recurring charges
- **Tenancy lifecycle** — create, activate, and end tenancies, with tenancy-specific charges and security deposits
- **Billing & payments** — invoices with line items, payment recording and confirmation, payment allocation across invoices, and refunds
- **Agent commissions** — commission tracking tied to owners/agents
- **Maintenance** — maintenance tickets with assignment and resolution workflow
- **Listings** — public listing publish/approve/take-down workflow, separate from internal unit records
- **Notifications** — per-user notifications with mark-as-read
- **Role-based access** — Sanctum token auth with an admin-only registration endpoint and route-level role middleware

## Tech stack

- **Framework:** Laravel 13 (PHP 8.3)
- **Database:** PostgreSQL
- **Auth:** Laravel Sanctum (token-based)
- **Testing:** PHPUnit

## API overview

All endpoints below sit under `/api` and (aside from `/login`) require a Sanctum bearer token.

```
POST   /login
POST   /logout
GET    /me
POST   /register                          (admin only)

/owners, /agents, /tenants                standard CRUD
/properties, /units, /unit-charges        standard CRUD
/tenancies, /tenancy-charges              standard CRUD
POST   /tenancies/{id}/activate
POST   /tenancies/{id}/end

/deposits, /vacate-notices                standard CRUD
/invoices, /invoice-items                 standard CRUD
/payments, /payment-allocations, /refunds standard CRUD
POST   /payments/{id}/confirm

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
php artisan serve
```

## Related

- [rems-frontend](https://github.com/Gabriel-Bjay/rems-frontend) — Angular client for this API

## License

MIT
