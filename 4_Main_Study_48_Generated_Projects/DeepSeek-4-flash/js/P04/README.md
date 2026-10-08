# P04 — Hotel Booking System (JavaScript)

A complete, integrated, runnable reservation-service web application. Guests search rooms, book stays with simulated payment, manage and cancel bookings, message staff, and review completed stays. Staff check guests in/out, manage inventory and availability blocks, and issue invoices. An admin exports occupancy, revenue, and cancellation reports. The frontend handles unavailable dates, conflicts, and validation states gracefully.

This is a synthetic benchmark application aligned with the *Cal.com* workflow anchors (availability slots, booking links, appointment creation, rescheduling, cancellation, attendee details, staff calendars, notifications) but implemented independently.

## Technology profile (authoritative)

| Concern | Choice |
| --- | --- |
| Language | JavaScript (ECMAScript modules) |
| Runtime | Node.js 22 LTS |
| Web framework | Express 5 (`express@5.1.0`) |
| Database | SQLite via `better-sqlite3@11.10.0` behind a repository/data-access layer |
| Authentication | Server-side sessions, HTTP-only cookie, session records stored in SQLite |
| Browser client | Server-served HTML + EJS, CSS, vanilla JS (no client framework) |
| Real-time transport | `ws@8.18.0` WebSocket adapter (`/ws`) for live booking/message events |
| Package tooling | npm with committed `package-lock.json`, pinned direct versions |
| Configuration | Environment variables, documented in `.env.example` |
| Containerization | `Dockerfile` + `docker-compose.yml` |

External services (email, payment, WebSocket) are deterministic local adapters: email writes to the `outbound_notifications` table, payments are simulated and masked, and no paid account is required.

## Prerequisites

- Node.js 22 LTS (tested on 22.21.0) and npm 11+
- Optionally Docker + Docker Compose for containerized execution

## Setup

```bash
npm install          # installs pinned dependencies (generates package-lock.json)
```

Configure the environment (optional; defaults work out of the box):

```bash
copy .env.example .env   # Windows
# or on macOS/Linux: cp .env.example .env
```

Documented variables (see `.env.example`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `3000` | HTTP port |
| `HOST` | `0.0.0.0` | Bind address |
| `PUBLIC_BASE_URL` | `http://localhost:3000` | Base URL used in generated reset links |
| `NODE_ENV` | `development` | In development, reset links are echoed to the browser for offline testing |
| `SESSION_TTL_MINUTES` | `480` | Session lifetime in minutes |
| `DB_PATH` | `./data/hotel.db` | SQLite database file |

## Database reset and seed

```bash
npm run reset   # deletes the database, recreates the schema, and seeds deterministic fixtures
npm run seed    # seeds again on an existing schema (idempotent, runs inside a transaction)
```

The seed creates 6 accounts, 10 rooms, maintenance blocks, bookings in every workflow state (confirmed, checked_in, checked_out, cancelled), payments, invoices, published and pending reviews, a message thread, and audit history. Dates are computed relative to the current day so availability demos always work offline.

## Start

```bash
npm start          # production-style start
npm run dev        # auto-restart on change (node --watch)
```

Open http://localhost:3000.

## Docker

```bash
docker compose up --build
# or:
docker build -t p04-hotel .
docker run -p 3000:3000 -v hotel-data:/app/data p04-hotel
```

The container seeds the database automatically on first start. The SQLite file lives on the `hotel-data` volume.

## Seed accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@hotel.test` | `admin123` |
| Staff | `staff@hotel.test` | `staff123` |
| Moderator | `moderator@hotel.test` | `mod123` |
| Guest (has completed stay, review, invoice) | `guest@hotel.test` | `guest123` |
| Guest (confirmed booking) | `alice@hotel.test` | `alice123` |
| Guest (completed stay, pending review) | `bob@hotel.test` | `bob123` |

## Usage walkthrough

- **Search** — set dates/guests/price/type on `/search`; results only show rooms free for the whole span.
- **Book** — sign in as a guest, pick a room on `/book`, enter contact + simulated card details. Idempotency keys prevent duplicate submissions.
- **Manage** — on `/bookings` guests cancel or reschedule confirmed bookings; every privileged action is written to the audit trail.
- **Staff check-in/out** — `/staff/check-in` lists arrivals and departures; changes stream over WebSocket.
- **Inventory** — `/staff/inventory` manages rooms, rates, and maintenance/availability blocks.
- **Messages** — `/messages` lets guests send special requests and staff reply; new messages arrive in real time.
- **Reviews** — guests review completed stays (`/reviews`); moderators publish or reject pending ones.
- **Invoices** — `/invoices` generates invoices and printable receipts for checked-out stays and exports CSV.
- **Reports** — `/admin/reports` exports occupancy, revenue, and cancellation reports as CSV.
- **API errors** — `/errors` exercises unavailable dates, conflicts, validation, unauthorized, and forbidden states against the real API.

## API contract

Every use case is exposed under `GET /api/hotel/<module>`, `POST /api/hotel/<module>`, and `PATCH /api/hotel/<module>/:id` with JSON bodies, deterministic error bodies (`{ error: { code, message, details? } }`), and status codes: `400 VALIDATION_ERROR`, `401 UNAUTHORIZED`, `403 FORBIDDEN`, `404`, `409 ROOM_UNAVAILABLE/INVALID_STATE`, `500 INTERNAL_ERROR` (no stack traces exposed). Also `GET /api/health`.

## Use-case traceability

| Use case | Title | API module | Main files |
| --- | --- | --- | --- |
| HOTEL-01 | Account access | `/api/hotel/account_access` | `src/controllers/accountAccessController.js`, `src/services/authService.js`, `src/services/sessionService.js`, `views/{login,register,recover,reset,account}.ejs` |
| HOTEL-02 | Room search | `/api/hotel/room_search` | `src/controllers/roomSearchController.js`, `src/services/roomService.js`, `views/search.ejs` |
| HOTEL-03 | Room details | `/api/hotel/room_details` | `src/controllers/roomDetailsController.js`, `src/services/roomService.js`, `views/roomDetail.ejs` |
| HOTEL-04 | Booking creation | `/api/hotel/booking_creation` | `src/controllers/bookingCreationController.js`, `src/services/bookingService.js`, `views/book.ejs` |
| HOTEL-05 | Booking management | `/api/hotel/booking_management` | `src/controllers/bookingManagementController.js`, `src/services/bookingService.js`, `src/services/auditService.js`, `views/bookings.ejs` |
| HOTEL-06 | Staff check-in/out | `/api/hotel/staff_check_in_out` | `src/controllers/staffCheckInOutController.js`, `src/services/bookingService.js`, `views/staffCheckIn.ejs` |
| HOTEL-07 | Room inventory | `/api/hotel/room_inventory` | `src/controllers/roomInventoryController.js`, `src/services/roomService.js`, `views/staffInventory.ejs` |
| HOTEL-08 | Guest messages | `/api/hotel/guest_messages` | `src/controllers/guestMessagesController.js`, `src/services/messageService.js`, `views/messages.ejs` |
| HOTEL-09 | Reviews | `/api/hotel/reviews` | `src/controllers/reviewsController.js`, `src/services/reviewService.js`, `views/reviews.ejs` |
| HOTEL-10 | Invoice and receipt | `/api/hotel/invoice_and_receipt` | `src/controllers/invoiceController.js`, `src/services/invoiceService.js`, `views/invoices.ejs` |
| HOTEL-11 | Admin reports | `/api/hotel/admin_reports` | `src/controllers/adminReportsController.js`, `src/services/reportService.js`, `views/adminReports.ejs` |
| HOTEL-12 | Frontend API integration and errors | `/api/hotel/frontend_api_integration_and_errors` | `src/controllers/frontendErrorsController.js`, `views/errorsDemo.ejs`, `public/js/pages/errors-demo.js` |

Shared plumbing: `src/app.js`, `src/server.js`, `src/config.js`, `src/db/{schema,database,seed}.js`, `src/middleware/{auth,validation,errors}.js`, `src/services/wsHub.js`, `public/css/styles.css`, `public/js/main.js`.

## Smoke check

```bash
npm run reset
npm start            # in one terminal
# in another:
curl http://localhost:3000/api/health
curl -X POST http://localhost:3000/api/hotel/account_access -H "Content-Type: application/json" -d '{"action":"login","email":"guest@hotel.test","password":"guest123"}'
curl "http://localhost:3000/api/hotel/room_search?capacity=2"
curl "http://localhost:3000/api/hotel/room_details?id=3"
```
