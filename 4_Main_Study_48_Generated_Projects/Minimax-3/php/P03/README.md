# P03 E-commerce System

A complete, runnable synthetic benchmark e-commerce web application aligned with
the workflow categories of **PrestaShop 8.1.0**. Built with **PHP 8.3 / Slim 4 /
SQLite** and **vanilla HTML / CSS / JavaScript** served from the server.

This project implements all 13 use cases (`SHOP-01` … `SHOP-13`) end-to-end with
deterministic fixtures, role-based access, persistent sessions stored in SQLite,
and a deterministic local payment / email / upload fixture layer so the entire
application runs offline.

## Quick start

```bash
# 1. Install PHP dependencies (Slim 4, PSR-7, phpdotenv)
composer install

# 2. Initialize the SQLite database and load deterministic seed data
php bin/seed.php

# 3. Start the local HTTP server
php -S 127.0.0.1:8080 -t public public/index.php
# or simply:
composer serve
```

Visit <http://127.0.0.1:8080/> and sign in with one of the seeded accounts
listed below.

### Docker

```bash
docker compose up --build
# Visit http://127.0.0.1:8080/
```

The container runs `bin/seed.php` on startup so a fresh SQLite database is
created on first launch and persisted via the `data/` volume mount.

## Seeded accounts

| Role       | Email                       | Password         |
|------------|-----------------------------|------------------|
| Admin      | `admin@example.com`         | `Admin#12345`    |
| Seller     | `seller@example.com`        | `Seller#12345`   |
| Moderator  | `moderator@example.com`     | `Admin#12345`-ish — actually `Moderator#12345` |
| Customer   | `customer@example.com`      | `Customer#12345` |
| Customer   | `shopper@example.com`       | `Shopper#12345`  |

Each account is automatically redirected to its appropriate dashboard after
sign-in (`/admin/operations`, `/seller/catalog`, `/reviews/moderation`, or
`/customer/data`).

## Available commands

| Command | Purpose |
|---------|---------|
| `composer install` | Install pinned dependencies from `composer.lock` |
| `composer seed`    | Run `php bin/seed.php` (reset + seed SQLite)  |
| `composer serve`   | Run the PHP built-in HTTP server on port 8080 |
| `php bin/seed.php` | Reset the database file and load deterministic seed fixtures |
| `php -S 127.0.0.1:8080 -t public public/index.php` | Start the application manually |
| `docker compose up --build` | Build and run the containerised application |

## Architecture

| Layer | Implementation |
|-------|----------------|
| Web framework | Slim 4 (routing + PSR-7 middleware) |
| Persistence | SQLite via PDO + dedicated repository classes (`src/Models/`) |
| Auth | HTTP-only session cookie, server-side session records persisted in `sessions` table (`src/Auth/Session.php`) |
| Templating | Server-rendered PHP templates in `templates/` (vanilla CSS / JS, no client-side framework) |
| Deterministic external services | `src/Support/Payment.php` (always_approve / always_decline / tok_decline), `src/Support/Mailer.php` (writes to `data/outbox.log`), `src/Support/Storage.php` (SVG placeholder image uploads) |
| Configuration | `.env` (committed `.env.example` shows all keys) |
| Dependency manager | Composer with pinned versions in `composer.lock` |

All application state lives in `data/shop.sqlite`. Deleting this file (or
running `php bin/seed.php`) returns the system to a known starting state.

## Configuration (`.env`)

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080

DB_PATH=data/shop.sqlite

SESSION_NAME=SHOP_SESSION
SESSION_LIFETIME=86400

PAYMENT_GATEWAY=fixture
PAYMENT_FIXTURE_MODE=always_approve

EMAIL_TRANSPORT=fixture-log
EMAIL_LOG_PATH=data/outbox.log
```

## Use-case implementation map

Each use case from the prompt maps to a controller, a set of HTTP routes,
repository methods, and template files. Use this table to navigate the
codebase.

| Use case | Title | Main implementation files / routes |
|----------|-------|------------------------------------|
| SHOP-01 | Accounts | `src/Controllers/Accounts.php` — `/login`, `/register`, `/logout`, `/password/forgot`, `/password/reset`, `/account`, `/api/shop/accounts` |
| SHOP-02 | Catalog search | `src/Controllers/CatalogSearch.php` — `/catalog`, `/products/{id}`, `/api/shop/catalog_search` |
| SHOP-03 | Reviews | `src/Controllers/Reviews.php` — `/reviews/moderation`, `/api/shop/reviews` |
| SHOP-04 | Catalog management | `src/Controllers/CatalogManagement.php` — `/seller/catalog`, `/api/shop/catalog_management` |
| SHOP-05 | Shopping cart | `src/Controllers/ShoppingCart.php` — `/cart`, `/cart/items/{id}`, `/cart/items/{id}/delete`, `/cart/promotion`, `/api/shop/shopping_cart` |
| SHOP-06 | Checkout | `src/Controllers/Checkout.php` — `/checkout`, `/api/shop/checkout` |
| SHOP-07 | Order access | `src/Controllers/OrderAccess.php` — `/orders`, `/orders/{id}`, `/api/shop/order_access` |
| SHOP-08 | Order lifecycle | `src/Controllers/OrderLifecycle.php` — `/orders/{id}/lifecycle`, `/api/shop/order_lifecycle` |
| SHOP-09 | Inventory | `src/Controllers/Inventory.php` — `/inventory`, `/inventory/{id}`, `/api/shop/inventory` |
| SHOP-10 | Seller and admin operations | `src/Controllers/SellerAdminOps.php` — `/admin/operations`, `/api/shop/seller_and_administrator_operations` |
| SHOP-11 | Customer data | `src/Controllers/CustomerData.php` — `/customer/data`, `/api/shop/customer_data` |
| SHOP-12 | Reports | `src/Controllers/Reports.php` — `/reports`, `/reports/export.csv`, `/api/shop/reports` |
| SHOP-13 | Frontend API integration | `src/Controllers/FrontendApi.php` — `/frontend/api`, `/api/shop/frontend_api_integration` |

Shared supporting code:

| Concern | File |
|---------|------|
| Routing & middleware | `src/Bootstrap.php` |
| Database / schema | `src/Database.php`, `src/schema.sql` |
| Session management | `src/Auth/Session.php` |
| View rendering | `src/Support/View.php` |
| CSRF helpers | `src/Support/Csrf.php` |
| Validation helpers | `src/Support/Validator.php` |
| HTML layout / assets | `templates/layout.php`, `public/css/styles.css`, `public/js/app.js` |
| Seed fixtures | `bin/seed.php` |

## Workflows

* **Browse catalog** — `/catalog` shows published products. Filter by keyword,
  category, price range, and stock. Add to cart from the product detail page.
* **Place an order** — Add items to the cart, apply a promotion code (`WELCOME10`,
  `SUMMER20`), complete the checkout form. The order is persisted, payment is
  simulated by the fixture, inventory is decremented, and the customer receives
  an `outbox.log` notification.
* **Track an order** — Customer can view their orders at `/orders`. Seller and
  admin see orders scoped to their role. Order lifecycle transitions (`paid`,
  `shipped`, `delivered`, `cancelled`, `refunded`) are governed by the
  transition map in `src/Support/Validator.php`.
* **Privileged workflows** — Sellers manage products at `/seller/catalog` and
  inventory at `/inventory`. Admins manage users, promotions, and settings at
  `/admin/operations`. Moderators approve / reject reviews at
  `/reviews/moderation`. All privileged actions are recorded in the
  `audit_events` table.
* **Reports** — Sellers / admins can download CSV exports from `/reports`
  (sales, inventory, customers).

## Local fixtures

| Service | Implementation | Toggle |
|---------|----------------|--------|
| Payment | `src/Support/Payment.php` — `always_approve` (default), `always_decline`, or per-token (`tok_decline`) | `PAYMENT_FIXTURE_MODE` env |
| Email | `src/Support/Mailer.php` — writes to `data/outbox.log` and the `outbox` table | `EMAIL_LOG_PATH` env |
| Image upload | `src/Support/Storage.php` — validates PNG/JPEG/GIF/WebP, saves to `data/uploads/`; defaults to a deterministic SVG placeholder when no file is supplied | n/a |

These adapters keep the application fully offline; the `data/` directory is the
single source of truth for persistent state.

## Browser smoke check

After `composer serve`, the following pages should render without errors:

| Path | Description |
|------|-------------|
| `/` | Home (featured products, categories) |
| `/login`, `/register` | Sign in / sign up |
| `/catalog`, `/products/{id}` | Catalog search + product detail |
| `/cart`, `/checkout` | Cart and checkout |
| `/orders`, `/orders/{id}`, `/orders/{id}/lifecycle` | Order list, detail, lifecycle |
| `/customer/data` | Address book, preferences |
| `/seller/catalog`, `/inventory`, `/inventory/{id}` | Catalog / inventory management |
| `/reviews/moderation` | Review queue (moderator) |
| `/admin/operations` | User / promotion / settings management (admin) |
| `/reports`, `/reports/export.csv?type=...` | Reports and CSV export |
| `/frontend/api` | Frontend API integration page |
| `/api/shop/...` | JSON API counterparts for every use case |

The included `bin/smoke_http.ps1` runs 42 end-to-end checks covering all 13 use
cases (browse → order → lifecycle → reports). To re-run after a change:

```bash
php bin/seed.php
# start the server in another shell or background, then:
powershell -ExecutionPolicy Bypass -File bin/smoke_http.ps1
```

## Notes

* The application is intentionally self-contained — no third-party calls,
  no remote payments, no remote email providers, no external storage.
* `session_regenerate_id(true)` is performed on login and the database session
  row is re-keyed accordingly.
* All write endpoints are protected by both role checks and CSRF tokens.
* Errors raised by missing authentication / authorization are converted to
  HTTP responses (401 / 403 JSON for API routes, 302 redirects for HTML routes)
  via dedicated exception types in `src/Controllers/`.