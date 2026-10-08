# Northstar Market — P03 E-commerce System

Northstar Market is a complete offline e-commerce workflow application built with PHP 8.3, Slim 4, PSR-7, PDO/SQLite, persistent server-side sessions, server-rendered browser assets, and vanilla JavaScript. It integrates customer, seller, moderator, and administrator workflows in one data model.

## Prerequisites and startup

- PHP 8.3 with `pdo_sqlite` and `fileinfo`
- Composer 2.8+

```bash
cp .env.example .env
composer install
composer db:reset
composer start
```

Open <http://localhost:8080>. The health endpoint is <http://localhost:8080/health>.

The browser workspace provides catalog search and role-specific customer, seller, moderator, and administrator panels. Its API console can invoke any remaining workflow route with JSON input.

## Deterministic seed accounts

All accounts use password `Password123!`.

| Actor | Email | ID | Seed state |
| --- | --- | ---: | --- |
| Customer | `customer@example.test` | 1 | Address 1, shipped order 1, product 2 in wishlist |
| Second customer | `customer2@example.test` | 2 | Address 2, shipped order 2, review 1 |
| Approved seller | `seller@example.test` | 3 | Northstar Goods; owns products 1 and 2 |
| Pending seller | `seller2@example.test` | 4 | Pending Market; owns draft product 3 |
| Administrator | `admin@example.test` | 5 | Seller approval, product moderation, promotion, refund access |
| Moderator | `moderator@example.test` | 6 | Review visibility moderation |

The database also contains three categories, three products, four variants, two completed purchases, shipments, a public review, two active carts, and the promotion code `WELCOME10`.

To discard local changes and restore all fixtures:

```bash
composer db:reset
```

## Offline adapters and state rules

- Payment uses `mock-card` or `mock-wallet` and stores only a deterministic local provider reference.
- Product images are stored under `UPLOAD_DIR`; metadata is stored in SQLite. JPEG, PNG, and WebP are accepted.
- Checkout rechecks price and stock inside one transaction. Order creation, stock decrement, payment capture, and cart clearing commit together.
- `(customer_id, idempotency_key)` uniquely identifies checkout requests; a repeated key returns the original order.
- Stock adjustments are append-only and unique by `(variant_id, reference)`.
- A paid order may be cancelled before shipment. Paid or shipped orders may enter refund review. Only an administrator completes a refund. Multi-seller orders become shipped after every participating seller records a shipment.
- Public catalog routes expose only active products. Customer, seller, address, cart, wishlist, review, inventory, and order mutations are scoped from the authenticated session.

## Main API surface

JSON failures use a stable shape:

```json
{"error":{"code":"validation_failed","message":"...","fields":{}}}
```

Typical statuses are `201` for creation, `200` for reads and actions, `204` for deletion, `401` for missing authentication, `403` for role or ownership denial, `404` for an in-scope missing resource, `409` for state conflicts, and `422` for invalid input.

## Docker

```bash
docker compose up --build
```

Open <http://localhost:8080>. The named `shop-data` volume preserves SQLite data and product images. Restore deterministic fixtures inside the running container with:

```bash
docker compose exec shop php bin/reset-database.php
```

## Use-case traceability

| ID | Main implementation |
| --- | --- |
| SHOP-01 Accounts and profiles | `src/AuthProfileRoutes.php`; `/api/auth/*`, `/api/profile` |
| SHOP-02 Catalog search | `src/CatalogRoutes.php`; `GET /api/products*` |
| SHOP-03 Product reviews | `src/CatalogRoutes.php`; product review and moderation routes |
| SHOP-04 Catalog management | `src/SellerAdminRoutes.php`; seller product/image and admin category routes |
| SHOP-05 Shopping cart | `src/CartCheckoutRoutes.php`; `/api/cart*` |
| SHOP-06 Checkout | `src/CartCheckoutRoutes.php`; checkout preview and order creation |
| SHOP-07 Order access | `src/OrderRoutes.php`; customer, seller, and administrator order projections |
| SHOP-08 Order lifecycle | `src/OrderRoutes.php`; cancellation, shipment, refund request, and refund actions |
| SHOP-09 Inventory | `src/SellerAdminRoutes.php`; seller inventory and append-only adjustments |
| SHOP-10 Seller/admin operations | `src/SellerAdminRoutes.php`; storefront, approval, and product moderation |
| SHOP-11 Customer data/addresses | `src/AuthProfileRoutes.php`; address CRUD, profile export, deletion requests |
| SHOP-12 Sales reports | `src/SellerAdminRoutes.php`; seller-scoped and platform reports |
| SHOP-13 Wishlist/promotions | `src/CartCheckoutRoutes.php`; wishlist, promotion administration and application |

Shared persistence and projections are implemented in `database/schema.sql`, `src/ShopRepository.php`, and `src/Seeder.php`. The browser workspaces are in `public/app.html`, `public/assets/app.css`, and `public/assets/app.js`.

## Functional acceptance tests

Run all use-case tests with:

```bash
composer functional:test
```

Each file creates its own deterministic temporary SQLite database and upload directory, then sends PSR-7 requests through the real Slim application. The suite checks successful workflows, validation, role and ownership boundaries, server-calculated totals, persistent state changes, stock effects, order transitions, seller report scope, natural uniqueness, and checkout idempotency.

| Use case | Functional test |
| --- | --- |
| SHOP-01 | `tests/Functional/SHOP01AccountsAndProfilesTest.php` |
| SHOP-02 | `tests/Functional/SHOP02CatalogSearchTest.php` |
| SHOP-03 | `tests/Functional/SHOP03ProductReviewsTest.php` |
| SHOP-04 | `tests/Functional/SHOP04CatalogManagementTest.php` |
| SHOP-05 | `tests/Functional/SHOP05ShoppingCartTest.php` |
| SHOP-06 | `tests/Functional/SHOP06CheckoutTest.php` |
| SHOP-07 | `tests/Functional/SHOP07OrderAccessTest.php` |
| SHOP-08 | `tests/Functional/SHOP08OrderLifecycleTest.php` |
| SHOP-09 | `tests/Functional/SHOP09InventoryTest.php` |
| SHOP-10 | `tests/Functional/SHOP10SellerAdminOperationsTest.php` |
| SHOP-11 | `tests/Functional/SHOP11CustomerDataAddressesTest.php` |
| SHOP-12 | `tests/Functional/SHOP12SalesReportsTest.php` |
| SHOP-13 | `tests/Functional/SHOP13WishlistPromotionsTest.php` |

The runner requires no additional testing framework. These files are business-level functional acceptance tests, not security or penetration tests.

## Project layout

```text
bin/reset-database.php       deterministic database reset and seed
bin/run-functional-tests.php dependency-free functional test runner
database/schema.sql          SQLite schema, constraints, and indexes
public/                      browser application and Slim entry point
src/                         routes, authentication, repository, persistence helpers
tests/Functional/            one isolated functional test file per use case
var/product-images/          local object-storage adapter
```
