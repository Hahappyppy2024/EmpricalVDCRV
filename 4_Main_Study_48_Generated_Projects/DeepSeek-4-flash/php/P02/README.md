# P02 — Conference Review System

A complete, integrated, runnable web application implementing the synthetic
**Conference Review System** benchmark (Version A), aligned at a high level with
Open Journal Systems (OJS) workflow anchors: conference setup, submission,
editorial phases, reviewer assignment, peer review, rebuttals, decisions,
double-blind views, and bulk exports.

This is a synthetic benchmark application, not a clone of OJS.

## Technology stack

| Concern | Choice |
| --- | --- |
| Language / runtime | PHP 8.3 |
| Web framework | Slim 4 (PSR-7 request/response) |
| Database | SQLite via PDO (repository / data-access layer) |
| Authentication | Server-side sessions stored in SQLite; HTTP-only cookie `conf_session` |
| Browser client | Server-served HTML, CSS, vanilla JavaScript |
| Real-time transport | Workerman WebSocket process (shares the SQLite DB) + HTTP polling fallback |
| Dependency tooling | Composer with locked `composer.lock` |
| Containerization | Dockerfile + `docker-compose.yml` |

## What is implemented

All 12 use cases are implemented as integrated workflows sharing one account,
session, entity, and permission model:

| UC | Use case | Primary actors |
| --- | --- | --- |
| CONF-01 | Account access and recovery | Author, reviewer, chair, admin |
| CONF-02 | Conference phases | Chair |
| CONF-03 | Paper submission | Author |
| CONF-04 | Submission discovery | Author, chair |
| CONF-05 | Manuscript access | Author, reviewer, chair |
| CONF-06 | Reviewer assignment | Chair |
| CONF-07 | Reviewing | Reviewer |
| CONF-08 | Rebuttal | Author, reviewer |
| CONF-09 | Decision management | Chair |
| CONF-10 | Double-blind views | Author, reviewer, chair |
| CONF-11 | Bulk exports | Chair |
| CONF-12 | Frontend API integration and errors | User |

## Prerequisites

- PHP 8.3 (with `pdo_sqlite`, `json`, `mbstring`)
- Composer 2
- Optional: Docker + Docker Compose
- Optional for live WebSocket broadcasting: `ext-pcntl` or `ext-event`
  (available in the Docker image; on Windows local without them the browser
  falls back to HTTP polling automatically)

## Setup

```bash
composer install
cp .env.example .env        # defaults are valid for local execution
```

Environment variables are documented in `.env.example`.

## Database reset / seed

```bash
php bin/reset_db.php        # drop schema + apply schema.sql + seed fixtures
```

The SQLite file is `database/conference.sqlite` by default (see `DB_PATH`).

Seed fixtures: 6 users (one per role and duplicates), 4 conference phases,
5 paper submissions with manuscript PDFs, 6 reviewer assignments, 4 reviews,
2 rebuttals, 2 decisions, audit events, a seed export, blind-view records,
account-access records, discovery log, and real-time events.

### Seed accounts

| Role | Email | Password |
| --- | --- | --- |
| admin | `admin@example.com` | `Admin@123` |
| chair | `chair@example.com` | `Chair@123` |
| author | `author@example.com` | `Author@123` |
| author | `author2@example.com` | `Author2@123` |
| reviewer | `reviewer@example.com` | `Reviewer@123` |
| reviewer | `reviewer2@example.com` | `Reviewer2@123` |

## Startup

Start the HTTP server (PHP built-in server) and the WebSocket process:

```bash
php bin/serve.php          # starts HTTP on 127.0.0.1:8080 and spawns the WS process
```

Or manually:

```bash
php -S 127.0.0.1:8080 -t public public/index.php   # HTTP
php bin/websocket.php                               # WebSocket on 127.0.0.1:8090
```

Open http://127.0.0.1:8080 and sign in with a seed account.

If port 8080 is already in use, start on another port:
`php bin/serve.php --port=8081`.

## Docker

```bash
docker compose up --build
```

Open http://localhost:8080. The container initializes the database on boot,
starts the Workerman WebSocket server on port 8090 (live broadcasting works
because the image has the needed extensions), and serves HTTP on 8080.

## Smoke check

A CLI smoke script exercises representative API flows (login, submissions,
discovery, manuscript download, reviewer assignment, reviews, rebuttals,
decisions, exports, blind views, errors, sign-out):

```bash
php bin/reset_db.php && php bin/smoke.php
```

Expected: `Smoke check complete: 30 passed, 0 failed`.

## API and pages

All HTML pages are served from `/`; every interactive action talks to the JSON
API below. Responses use a stable envelope:

```json
{ "ok": true,  "data": { ... } }
{ "ok": false, "error": { "code": "...", "status": 422, "message": "...", "details": {} } }
```

Error codes: `auth_required`, `auth_error`, `permission_error`, `not_found`,
`validation_error`, `conflict_error`, `phase_error`, `internal_error`.

Every `/api/conf/{module}` route supports `GET` (list), `POST` (create), and
`PATCH /api/conf/{module}/{id}` (update). Modules:

| Module | Use case |
| --- | --- |
| `account_access_and_recovery` | CONF-01 |
| `conference_phases` | CONF-02 |
| `paper_submission` | CONF-03 |
| `submission_discovery` | CONF-04 |
| `manuscript_access` | CONF-05 |
| `reviewer_assignment` | CONF-06 |
| `reviewing` | CONF-07 |
| `rebuttal` | CONF-08 |
| `decision_management` | CONF-09 |
| `double_blind_views` | CONF-10 |
| `bulk_exports` | CONF-11 |
| `frontend_api_integration_and_errors` | CONF-12 |
| `realtime_events` | live event feed (`?since=<id>`) |

Extra routes:

- `GET /api/conf/manuscript_access/{submission_id}/download` — stream a manuscript file
- `GET /api/conf/bulk_exports/{id}/download` — download a generated export
- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`,
  `POST /auth/reset`, `POST /auth/reset-confirm` — account access and recovery
- `GET /api/health`, `GET /healthz` — health/status

HTML pages: `/login`, `/register`, `/reset-password`, `/dashboard`, `/papers`,
`/papers/{id}`, `/discovery`, `/reviewer`, `/rebuttal`, `/account`, `/errors`,
`/manuscripts`, `/blind-views`, `/chair` (+ `/chair/phases`,
`/chair/assignments`, `/chair/decisions`, `/chair/exports`).

## Real-time events

Every mutation publishes an event into the SQLite `realtime_events` table
(`paper.submitted`, `reviewer.assigned`, `review.submitted`, `rebuttal.submitted`,
`decision.recorded`, `export.generated`, `phase.changed`).

- The browser connects to the WebSocket (`WS_URL`) and renders live toasts.
- If the WebSocket is unavailable (e.g. Windows local without `pcntl`/`event`),
  the client automatically polls `GET /api/conf/realtime_events?since=N`.

## Local adapters (deterministic, offline)

- **Email**: appended to `storage/mail.log` (reset tokens, decisions).
- **PDF**: dependency-free deterministic PDF generator (`PdfService`).
- **Exports**: generated locally into `storage/exports` (CSV/PDF).
- **Files**: uploaded manuscripts stored in `storage/manuscripts`.
- **Real-time**: SQLite-backed event log + Workerman/HTTP polling.

## Traceability table

| Use case | Primary files | Routes / entry points |
| --- | --- | --- |
| CONF-01 Account access and recovery | `src/Services/AuthService.php`, `src/Services/SessionService.php`, `src/Services/Workflows/AccountAccessRecoveryService.php`, `src/Controllers/AuthController.php` | `/login`, `/register`, `/reset-password`, `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/reset`, `/auth/reset-confirm`, `/api/conf/account_access_and_recovery` |
| CONF-02 Conference phases | `src/Services/Workflows/ConferencePhasesService.php`, `src/Services/PhaseService.php`, `src/Views/pages/conference_phases.php` | `/chair/phases`, `/api/conf/conference_phases` |
| CONF-03 Paper submission | `src/Services/Workflows/PaperSubmissionService.php`, `src/Services/FileService.php`, `src/Views/pages/paper_submission.php` | `/papers`, `/api/conf/paper_submission` |
| CONF-04 Submission discovery | `src/Services/Workflows/SubmissionDiscoveryService.php`, `src/Views/pages/submission_discovery.php` | `/discovery`, `/api/conf/submission_discovery` |
| CONF-05 Manuscript access | `src/Services/Workflows/ManuscriptAccessService.php`, `src/Views/pages/manuscript_access.php` | `/manuscripts`, `/api/conf/manuscript_access`, `/api/conf/manuscript_access/{id}/download` |
| CONF-06 Reviewer assignment | `src/Services/Workflows/ReviewerAssignmentService.php`, `src/Views/pages/reviewer_assignment.php` | `/chair/assignments`, `/api/conf/reviewer_assignment` |
| CONF-07 Reviewing | `src/Services/Workflows/ReviewingService.php`, `src/Views/pages/reviewing.php` | `/reviewer`, `/api/conf/reviewing` |
| CONF-08 Rebuttal | `src/Services/Workflows/RebuttalService.php`, `src/Views/pages/rebuttal.php` | `/rebuttal`, `/api/conf/rebuttal` |
| CONF-09 Decision management | `src/Services/Workflows/DecisionManagementService.php`, `src/Views/pages/decision_management.php` | `/chair/decisions`, `/api/conf/decision_management` |
| CONF-10 Double-blind views | `src/Services/Workflows/DoubleBlindViewsService.php`, `src/Views/pages/double_blind_views.php` | `/blind-views`, `/api/conf/double_blind_views` |
| CONF-11 Bulk exports | `src/Services/Workflows/BulkExportsService.php`, `src/Services/ExportService.php`, `src/Services/PdfService.php`, `src/Views/pages/bulk_exports.php` | `/chair/exports`, `/api/conf/bulk_exports`, `/api/conf/bulk_exports/{id}/download` |
| CONF-12 Frontend API integration and errors | `public/assets/js/app.js`, `src/Services/Workflows/FrontendApiService.php`, `src/Middleware/JsonErrorHandler.php`, `src/Views/pages/frontend_api.php` | `/errors`, `/api/conf/frontend_api_integration_and_errors` |

Supporting infrastructure: `src/Bootstrap.php`, `src/Routes/routes.php`,
`src/Routes/workflows.php`, `src/Middleware/{AuthMiddleware,RoleMiddleware}.php`,
`src/Repositories/*`, `database/schema.sql`, `database/seed.php`,
`bin/{reset_db.php,serve.php,websocket.php,smoke.php}`.

## Project structure

```
├── public/                 # docroot (index.php, assets/)
├── src/
│   ├── Config/             # env-based configuration
│   ├── Container/          # minimal PSR-11 container
│   ├── Controllers/        # auth, pages, workflows, health
│   ├── Middleware/         # auth, roles, JSON error handler
│   ├── Repositories/       # PDO data-access layer
│   ├── Routes/             # routes + workflow module registry
│   ├── Services/           # auth, sessions, phases, exports, realtime, PDF, mail
│   │   └── Workflows/      # one service per use case (CONF-01 … CONF-12)
│   └── Views/              # layouts + pages (server-rendered HTML)
├── database/
│   ├── schema.sql          # SQLite schema
│   └── seed.php            # deterministic seed fixtures
├── bin/                    # reset_db, serve, websocket, smoke
├── storage/                # manuscripts, exports, logs (gitignored)
├── docker/                 # container entrypoint
├── Dockerfile
└── docker-compose.yml
```
