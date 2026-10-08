# P03 — E-commerce System

A complete, runnable synthetic e-commerce web application (benchmark project aligned with
PrestaShop 8.1.0 workflows: catalog, product administration, cart, checkout, order lifecycle,
customer data, stock, promotions, and reports).

**Stack (authoritative):** PHP 8.3 · Slim 4 (PSR-7) · SQLite via PDO (repository layer) ·
server-side sessions in SQLite (HTTP-only cookie) · server-served HTML/CSS/vanilla JS ·
Workerman WebSocket for real-time order states · Composer.

The application runs fully offline after `composer install`. Email, payment, image storage,
and real-time transport all use deterministic local adapters.

---

## Table of contents

1. [Prerequisites](#1-prerequisites)
2. [Installation](#2-installation)
3. [Environment configuration](#3-environment-configuration)
4. [Database reset and seed](#4-database-reset-and-seed)
5. [Running the application](#5-running-the-application)
6. [Seed accounts](#6-seed-accounts)
7. [Real-time WebSocket server](#7-real-time-websocket-server)
8. [Docker](#8-docker)
9. [API contract](#9-api-contract)
10. [Deterministic behaviors](#10-deterministic-behaviors)
11. [Traceability table](#11-traceability-table)
12. [Project structure](#12-project-structure)

---

## 1. Prerequisites

- PHP **8.3+** (CLI) with the `pdo`, `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `openssl`
  and `curl` extensions enabled.
- [Composer](https://getcomposer.org) 2.x.
- A web browser (for the storefront pages) and `curl` (for the JSON API).
- Docker + Docker Compose (optional, for containerized execution).

Verify:

```bash
php -v            # PHP 8.3.x
php -m            # confirm pdo_sqlite, fileinfo, mbstring are present
composer --version
```

## 2. Installation

```bash
composer install
```

This installs the exact versions pinned in `composer.lock`:

| Package | Version |
| --- | --- |
| slim/slim | ^4.15 (4.15.2) |
| slim/psr7 | ^1.8 |
| vlucas/phpdotenv | ^5.6 |
| workerman/workerman | ^4.2 |

## 3. Environment configuration

Copy the example file and adjust if needed:

```bash
cp .env.example .env
```

All values have working local defaults:

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_PATH` | `data/shop.db` | SQLite database file |
| `APP_HOST` / `APP_PORT` | `0.0.0.0` / `8080` | Web server bind address |
| `APP_URL` | `http://localhost:8080` | Base URL (used in password-reset links) |
| `WS_HOST` / `WS_PORT` | `127.0.0.1` / `8081` | Workerman WebSocket bind address |
| `WS_URL` | `ws://127.0.0.1:8081` | WebSocket endpoint embedded in pages |
| `UPLOAD_DIR` | `public/uploads` | Product image upload directory |
| `LOG_FILE` | `storage/logs/error.log` | Runtime error log (no stack traces exposed to clients) |

## 4. Database reset and seed

```bash
php bin/reset_db.php     # deletes + recreates + seeds the SQLite database (idempotent)
php bin/seed.php         # seeds only if the database is empty
```

The seed fixtures provide deterministic data for every actor, role, category, product, review,
order lifecycle state, promotion, address, audit event, saved search and API client required by
the acceptance criteria.

## 5. Running the application

Web application (PHP built-in server, single process):

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Or on Windows:

```bat
bin\serve.bat
```

Open <http://localhost:8080>. A health endpoint is available at `GET /health`.

Real-time WebSocket (optional, required for live order-state updates, SHOP-08/SHOP-13):

```bash
php bin/ws_server.php start
```

The browser page automatically connects to `WS_URL` and live-updates order statuses
(order detail page, order lists, seller/admin order tables). A 5-second HTTP polling
fallback (`/api/shop/frontend_api_integration`) keeps the UI consistent when the
WebSocket process is not running.

## 6. Seed accounts

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@example.com` | `admin123` |
| Seller 1 | `seller1@example.com` | `seller123` |
| Seller 2 | `seller2@example.com` | `seller123` |
| Customer 1 | `customer1@example.com` | `customer123` |
| Customer 2 | `customer2@example.com` | `customer123` |
| Moderator | `moderator1@example.com` | `moderator123` |

## 7. Real-time WebSocket server

The Workerman process shares the same SQLite database as the web app. Whenever an order is
created or its status changes, the HTTP layer writes a broadcast row; the WebSocket process
polls the `broadcasts` table once per second and pushes events to subscribed clients.

```bash
php bin/ws_server.php start       # foreground on WS_HOST:WS_PORT
```

Client protocol (used by `public/js/app.js`):

- Connect to `WS_URL`, then send: `{"type":"subscribe","channel":"orders"}`
- The server replies `{"event":"subscribed","payload":{"channel":"orders"}}`
- Events: `order.created`, `order.status_changed` with
  `{"order_id":..,"number":"..","status":"..","previous":"..","total":..}`.

## 8. Docker

```bash
docker compose up --build
```

- Web app: <http://localhost:8080>
- WebSocket: `ws://localhost:8081` (used by the pages)

`docker compose up --build` starts the `app` service (resets + seeds the DB on start) and the
`ws` service. Both share the `sqlite_data` volume.

Useful one-off command:

```bash
docker compose run --rm app seed
```

## 9. API contract

Every use case exposes `GET /api/shop/<module>`, `POST /api/shop/<module>` and
`PATCH /api/shop/<module>/{id}` as specified. Responses are always:

```json
{"success": true,  "data": {...}}
{"success": false, "error": "...", "errors": {field: "..."}}
```

- JSON request bodies must be sent with `Content-Type: application/json`.
- State-changing requests on an authenticated session must include the session's CSRF token
  via the `X-CSRF-Token` header (or a `_csrf` form field). Anonymous requests (login/register)
  do not require a token.
- The login/register response includes `data.session.csrf_token` for API clients.

API smoke-check example (PowerShell/curl):

```bash
curl -c jar -H "Content-Type: application/json" \
  -d '{"action":"login","email":"customer1@example.com","password":"customer123"}' \
  http://localhost:8080/api/shop/accounts
```

Then reuse `jar` plus the returned `csrf_token` for all authenticated calls.

## 10. Deterministic behaviors

- **Payment simulation** (`src/PaymentService.php`): cards ending in `0002` are declined
  (`402`); all other card numbers are approved and produce a stable reference `PAY-<orderId>`.
- **Email** (`src/MailService.php`): reset links are stored in the `mail_logs` table instead of
  being sent over the network.
- **Image upload** (`src/ImageStore.php`): uploads are saved to `public/uploads`.
- **Real-time** (`bin/ws_server.php`): local Workerman process sharing the SQLite DB.
- **Errors**: user-facing errors never contain stack traces; details are written to
  `storage/logs/error.log`.
- **Order lifecycle** (`src/Service/OrderService.php`):
  `pending → paid/shipped/cancelled`, `paid → shipped/cancelled/refunded`,
  `shipped → delivered/refunded`, `delivered → refunded`, `cancelled → refunded`.
  Invalid transitions return `422` without partial persistence. Customers may only cancel
  their own pending/paid orders.

## 11. Traceability table

| Use case | Title | Browser pages | Routes (page / API) | Main implementation files |
| --- | --- | --- | --- | --- |
| SHOP-01 | Accounts | `/login`, `/register`, `/forgot`, `/reset/{token}`, `/account` | `GET,POST /api/shop/accounts`, `PATCH /api/shop/accounts/{id}` | `src/Controller/AuthController.php`, `src/Controller/ApiController.php` (`accounts`), `src/Service/AccountService.php`, `src/Auth.php`, `src/SessionManager.php` |
| SHOP-02 | Catalog search | `/catalog`, `/products/{id}` | `GET,POST /api/shop/catalog_search`, `PATCH /api/shop/catalog_search/{id}` | `src/Controller/PageController.php` (`catalog`), `src/Controller/ApiController.php` (`catalogSearch`), `src/Service/CatalogService.php` |
| SHOP-03 | Reviews | `/products/{id}` (form), `/admin/reviews` | `GET,POST /api/shop/reviews`, `PATCH /api/shop/reviews/{id}` | `src/Controller/PageController.php` (`submitReview`), `src/Controller/AdminController.php`, `src/Controller/ApiController.php` (`reviews`), `src/Service/ReviewService.php` |
| SHOP-04 | Catalog management | `/seller/products`, `/seller/products/new`, `/seller/products/{id}/edit` | `GET,POST /api/shop/catalog_management`, `PATCH /api/shop/catalog_management/{id}` | `src/Controller/SellerController.php`, `src/Controller/ApiController.php` (`catalogManagement`), `src/Service/CatalogManagementService.php` |
| SHOP-05 | Shopping cart | `/cart` | `GET,POST /api/shop/shopping_cart`, `PATCH /api/shop/shopping_cart/{id}` | `src/Controller/CartController.php`, `src/Controller/ApiController.php` (`shoppingCart`), `src/Service/CartService.php` |
| SHOP-06 | Checkout | `/checkout` | `GET,POST /api/shop/checkout`, `PATCH /api/shop/checkout/{id}` | `src/Controller/CartController.php`, `src/Controller/ApiController.php` (`checkout`), `src/Service/CheckoutService.php`, `src/PaymentService.php` |
| SHOP-07 | Order access | `/account/orders`, `/account/orders/{id}`, `/seller/orders` | `GET,POST /api/shop/order_access`, `PATCH /api/shop/order_access/{id}` | `src/Controller/AccountController.php`, `src/Controller/SellerController.php`, `src/Controller/ApiController.php` (`orderAccess`), `src/Service/OrderService.php` |
| SHOP-08 | Order lifecycle | `/account/orders/{id}` (cancel), `/seller/orders` (advance) | `GET,POST /api/shop/order_lifecycle`, `PATCH /api/shop/order_lifecycle/{id}` | `src/Controller/ApiController.php` (`orderLifecycle`), `src/Service/OrderService.php`, `src/Repository/BroadcastRepository.php`, `bin/ws_server.php` |
| SHOP-09 | Inventory | `/seller/inventory` | `GET,POST /api/shop/inventory`, `PATCH /api/shop/inventory/{id}` | `src/Controller/SellerController.php`, `src/Controller/ApiController.php` (`inventory`), `src/Service/InventoryService.php` |
| SHOP-10 | Seller & administrator operations | `/seller/promotions`, `/admin/users`, `/admin/settings`, `/admin/audit` | `GET,POST /api/shop/seller_and_administrator_operations`, `PATCH /api/shop/seller_and_administrator_operations/{id}` | `src/Controller/AdminController.php`, `src/Controller/SellerController.php`, `src/Controller/ApiController.php` (`sellerAdminOperations`), `src/Service/AdminService.php` |
| SHOP-11 | Customer data | `/account`, `/account/addresses` | `GET,POST /api/shop/customer_data`, `PATCH /api/shop/customer_data/{id}` | `src/Controller/AccountController.php`, `src/Controller/ApiController.php` (`customerData`), `src/Service/CustomerDataService.php` |
| SHOP-12 | Reports | `/seller/reports`, `/seller/reports/export/{type}` | `GET,POST /api/shop/reports`, `PATCH /api/shop/reports/{id}` | `src/Controller/SellerController.php`, `src/Controller/ApiController.php` (`reports`), `src/Service/ReportService.php` |
| SHOP-13 | Frontend API integration | all pages (JS) | `GET,POST /api/shop/frontend_api_integration`, `PATCH /api/shop/frontend_api_integration/{id}` | `public/js/app.js`, `public/css/style.css`, `src/Controller/ApiController.php` (`frontendApi`), `src/Service/FrontendApiService.php`, `bin/ws_server.php` |

## 12. Project structure

```
P03/
├── bin/                       # CLI: reset_db, seed, ws_server, serve.bat, docker_entrypoint.sh
├── data/                      # SQLite database (created at runtime)
├── public/
│   ├── index.php              # Front controller (serves static files + Slim app)
│   ├── css/style.css          # Storefront styling
│   ├── js/app.js              # Vanilla JS: WebSocket, checkout, validation (SHOP-13)
│   └── uploads/               # Seed images + uploaded product images
├── src/
│   ├── App.php                # Dependency wiring + all routes
│   ├── Database.php           # Schema (SQLite DDL) + deterministic seed fixtures
│   ├── SessionManager.php     # SQLite-backed session records (HTTP-only cookie)
│   ├── Controller/            # Page + JSON API controllers
│   ├── Service/               # One service module per use case (SHOP-01..13)
│   ├── Repository/            # PDO repository/data-access layer
│   ├── Middleware/            # Session, CSRF, page auth/role
│   ├── Handler/               # Deterministic, no-leak error handler
│   ├── Log/                   # PSR-3 file logger
│   └── ...                    # Auth, PaymentService (simulator), MailService, ImageStore, View, Config
├── views/                     # Server-rendered HTML templates (storefront, account, seller, admin)
├── storage/logs/              # Runtime error log
├── .env.example               # Documented environment variables
├── composer.json / composer.lock
├── Dockerfile
├── docker-compose.yml
└── README.md
```
