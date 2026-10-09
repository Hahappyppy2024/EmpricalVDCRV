# P04 — Hotel Booking System (Benchmark)

Synthetic hotel reservation system implementing twelve use cases (HOTEL-01 through HOTEL-12) on top of an Express 5 + SQLite stack. The application is built as a runnable web application only — no functional tests, security oracle files, or attack scripts are generated.

## Stack

- Node.js 22 LTS, ECMAScript modules
- Express 5 web framework
- SQLite via `better-sqlite3`
- Server-side sessions stored in SQLite, delivered via HTTP-only cookie
- Vanilla HTML/CSS/JavaScript frontend served by Express
- npm with a pinned dependency manifest

## Prerequisites

- Node.js >= 22.0.0
- npm >= 10

## Setup

```bash
npm install
cp .env.example .env       # optional; defaults work without it
npm run init               # create the SQLite schema
npm run seed               # populate deterministic fixtures
```

## Run

```bash
npm start                  # http://localhost:3000
```

A smoke check script verifies end-to-end behavior:

```bash
npm run smoke              # requires a running server on $PORT (default 3000)
```

## Database commands

```bash
npm run reset              # delete data/hotel.db and re-apply schema
npm run init               # apply schema without resetting data
npm run seed               # populate deterministic fixtures (idempotent)
```

## Docker

```bash
docker compose build
docker compose up          # http://localhost:3000
```

The image installs dependencies, applies the schema, and seeds data on build, then starts the server.

## Seed accounts

| Role      | Email                  | Password   |
|-----------|------------------------|------------|
| Guest     | alice@example.com      | guest123   |
| Guest     | bob@example.com        | guest123   |
| Guest     | charlie@example.com    | guest123   |
| Staff     | staff@example.com      | staff123   |
| Admin     | admin@example.com      | admin123   |
| Moderator | mod@example.com        | mod123     |

Seed fixtures include rooms, availability blocks, bookings in various statuses, messages, an approved review, an invoice, a receipt, and notifications. Bookings span past, present, and future dates so that staff check-in/out, reviews, and invoices all have realistic data.

## Use-case traceability

| Use case | Module / routes | Frontend page | Notes |
|---|---|---|---|
| HOTEL-01 Account access | `src/routes/account.js`, `src/services/session.js` | `public/pages/account.html` | register / login / recovery / sign out / profile update |
| HOTEL-02 Room search | `src/routes/rooms_search.js`, `src/services/rooms.js` | `public/pages/rooms.html` | date range, capacity, price, amenities filters |
| HOTEL-03 Room details | `src/routes/room_details.js` | `public/pages/room_detail.html` | room description, future bookings, approved reviews |
| HOTEL-04 Booking creation | `src/routes/booking_creation.js`, `src/services/bookings.js` | `public/pages/booking_new.html`, `public/pages/bookings.html` | guest must be signed in; simulated card last4 capture |
| HOTEL-05 Booking management | `src/routes/booking_management.js` | `public/pages/bookings.html` | modify / cancel with audit log |
| HOTEL-06 Staff check-in/out | `src/routes/staff_check.js` | `public/pages/staff_check.html` | staff or admin role required |
| HOTEL-07 Room inventory | `src/routes/room_inventory.js` | `public/pages/inventory.html` | create / update rooms, add availability blocks |
| HOTEL-08 Guest messages | `src/routes/messages.js`, `src/services/messages.js` | `public/pages/messages.html` | guest ↔ staff conversation scoped per booking |
| HOTEL-09 Reviews | `src/routes/reviews.js`, `src/services/reviews.js` | `public/pages/reviews.html`, `public/pages/review_new.html` | guest writes; moderator approves/rejects |
| HOTEL-10 Invoice & receipt | `src/routes/invoice.js`, `src/services/invoice.js` | `public/pages/invoice.html` | HTML invoice + receipt record |
| HOTEL-11 Admin reports | `src/routes/admin_reports.js`, `src/services/reports.js` | `public/pages/admin_reports.html` | occupancy / revenue / cancellations + CSV export |
| HOTEL-12 Frontend API integration & errors | `src/routes/frontend_api.js` | `public/pages/admin_reports.html` | scenario simulator (conflict / validation / unauthorized / network) |

## Project structure

```
P04/
├── package.json
├── .env.example
├── Dockerfile
├── docker-compose.yml
├── README.md
├── data/                     # SQLite database lives here (gitignored)
├── public/
│   ├── css/app.css
│   ├── js/app.js
│   └── pages/*.html          # one HTML page per actor-facing workflow
├── scripts/smoke.js          # npm run smoke
└── src/
    ├── server.js
    ├── config.js
    ├── middleware/auth.js
    ├── routes/
    │   ├── account.js
    │   ├── rooms_search.js
    │   ├── room_details.js
    │   ├── booking_creation.js
    │   ├── booking_management.js
    │   ├── staff_check.js
    │   ├── room_inventory.js
    │   ├── messages.js
    │   ├── reviews.js
    │   ├── invoice.js
    │   ├── admin_reports.js
    │   └── frontend_api.js
    ├── services/
    │   ├── audit.js
    │   ├── bookings.js
    │   ├── crypto.js
    │   ├── errors.js
    │   ├── http.js
    │   ├── invoice.js
    │   ├── messages.js
    │   ├── notify.js
    │   ├── reports.js
    │   ├── reviews.js
    │   ├── rooms.js
    │   ├── session.js
    │   └── validate.js
    └── db/
        ├── connection.js
        ├── init.js
        ├── reset.js
        ├── seed.js
        └── schema.sql
```

## Workflow notes

- All persistent entities (users, rooms, blocks, bookings, booking status log, audit events, messages, reviews, invoices, receipts, notifications, sessions) live in a single SQLite file at `data/hotel.db`.
- Cross-user access is rejected: a guest cannot read another guest's bookings, messages, invoices, or reviews; only moderators/admins see pending reviews; only staff/admins can update inventory or perform check-in/out.
- Privileged operations write to the `audit_events` table.
- A deterministic local-only "email" recovery and a simulated card payment processor are used; no network calls are made.
- No tests, security oracle files, or attack scripts are generated by this project.