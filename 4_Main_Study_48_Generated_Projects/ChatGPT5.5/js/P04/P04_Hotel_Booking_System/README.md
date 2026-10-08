# P04 Hotel Booking System

A complete offline-capable hotel booking application built with Node.js 22, Express 5, SQLite, server-side sessions, and a vanilla JavaScript browser client. The project implements all 12 specified use cases and 37 HTTP endpoints.

## Prerequisites

- Node.js 22 LTS and npm
- Docker with Compose (optional)

## Run locally

```bash
cp .env.example .env
npm ci
npm run db:reset
npm start
```

Open <http://localhost:8080>. The database reset is deterministic and may be run whenever a clean demo dataset is needed.

## Seed accounts

All accounts use password `Password123!`.

| Role | Email |
| --- | --- |
| Guest | `guest@example.test` |
| Other guest | `other@example.test` |
| Staff | `staff@example.test` |
| Admin | `admin@example.test` |
| Moderator | `moderator@example.test` |

The disabled fixture `disabled@example.test` exists to exercise rejected-login behavior. Local payment tokens are deterministic: `pm_success` succeeds and `pm_decline` is rejected. Password-reset tokens are returned only in development mode so the workflow remains fully testable offline.

## Functional tests and coverage

```bash
npm run test:functional
npm run test:coverage
```

The coverage command uses Node's built-in test runner, includes application code under `src/`, excludes the process bootstrap `src/server.js`, and fails when overall branch coverage is below 85%. The verified result is 12/12 passing tests with 90.48% branch coverage. See [COVERAGE_REPORT.md](COVERAGE_REPORT.md).

## Docker

```bash
docker compose up --build
```

The container uses Node.js 22 and persists SQLite data in the `hotel-data` volume. To rebuild the seeded volume, run `docker compose down -v` before starting again.

## Use-case traceability

| ID | Workflow | Main routes | Functional test |
| --- | --- | --- | --- |
| HOTEL-01 | Account access | `/api/auth/register`, `/login`, `/logout`, `/password-reset-requests`, `/password-resets` | `hotel01-account-access.test.js` |
| HOTEL-02 | Room search | `GET /api/room-types/availability` | `hotel02-room-search.test.js` |
| HOTEL-03 | Room details | `GET /api/room-types/:id`, `/rates` | `hotel03-room-details.test.js` |
| HOTEL-04 | Booking creation | `POST /api/booking-quotes`, `/api/bookings` | `hotel04-booking-creation.test.js` |
| HOTEL-05 | Booking management | `GET/PATCH /api/bookings`, `POST /cancel` | `hotel05-booking-management.test.js` |
| HOTEL-06 | Check-in/out | `POST /api/staff/bookings/:id/check-in`, `/check-out` | `hotel06-checkin-checkout.test.js` |
| HOTEL-07 | Room inventory | `/api/staff/rooms`, `/api/admin/rooms`, `/api/admin/room-types/:id` | `hotel07-room-inventory.test.js` |
| HOTEL-08 | Guest messages | `GET/POST /api/bookings/:id/messages` | `hotel08-guest-messages.test.js` |
| HOTEL-09 | Stay reviews | room reviews, booking review, edit, moderation routes | `hotel09-reviews.test.js` |
| HOTEL-10 | Invoice/receipt | `GET /api/bookings/:id/invoice`, `/receipt.pdf` | `hotel10-invoice-receipt.test.js` |
| HOTEL-11 | Hotel reports | `/api/admin/reports/occupancy`, `/revenue`, `/cancellations` | `hotel11-reports.test.js` |
| HOTEL-12 | Rates/policies | admin rate-plan and cancellation-policy CRUD routes | `hotel12-rates-policies.test.js` |

The primary implementation is in `src/app.js`; authentication/session handling is in `src/auth.js`; schema and deterministic fixtures are in `database/schema.sql` and `src/seed.js`; database access is encapsulated by `src/repository.js` and `src/database.js`.

## Deterministic implementation choices

- SQLite is the source of truth for users, sessions, bookings, inventory, payments, invoices, messages, reviews, and policies.
- Operations that update multiple records—booking, cancellation, and checkout—run inside database transactions.
- Optimistic `version` checks protect mutable booking, inventory, review, rate, and policy resources.
- Payment, reset-token delivery, and PDF receipt generation use local deterministic adapters; no paid service or external account is required.
- The client is a server-served single page that exposes public search plus role-aware guest/staff/admin workflows.
