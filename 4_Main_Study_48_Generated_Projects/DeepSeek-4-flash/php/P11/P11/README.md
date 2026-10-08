# P11 — Hosting Control Panel

A complete, runnable web application for hosting control panel management built with **PHP 8.3**, **Slim 4**, and **SQLite**. It implements all twelve use cases (HOST-01 … HOST-12) from the project specification: account access, domain management, site management, a file manager, database management, backup/restore, SSL certificates, scheduled tasks, resource usage, support tickets, audit logs, and admin operations.

This is a synthetic benchmark application aligned with the workflow anchors of the real-world *Froxlor* project (domains, web spaces, file manager, databases, certificates, backups, cron jobs, support tickets, quotas, and admin operations). It is an independent implementation and does not copy the real-world project.

---

## Technology stack

| Layer | Technology |
| --- | --- |
| Language / runtime | PHP 8.3 |
| Web framework | Slim 4 with PSR-7 request/response handling |
| Database | SQLite through PDO, accessed via a repository/data-access layer |
| Authentication | Server-side sessions identified by an HTTP-only cookie (`host_session`); persistent session records stored in SQLite |
| Browser client | Server-served HTML, CSS, and vanilla JavaScript |
| Real-time transport | Workerman WebSocket process (`bin/websocket.php`) sharing the same SQLite file, broadcasting audit events (optional) |
| Dependency tooling | Composer with exact direct dependency constraints and a committed `composer.lock` |
| Configuration | Environment variables documented in `.env.example` |

## Features

- **HOST-01 Account access** — register, sign in, sign out, profile update, persistent SQLite sessions, and account access event log.
- **HOST-02 Domain management** — add domains/subdomains/aliases, DNS records (A, AAAA, CNAME, MX, TXT), suspend/activate, delete.
- **HOST-03 Site management** — create sites, set document roots, view deployment status, deploy.
- **HOST-04 File manager** — browse, upload, create folders, rename, download, delete site files; cross-user access is blocked.
- **HOST-05 Database management** — create databases and manage database users with privileges.
- **HOST-06 Backup and restore** — create, upload, download, restore, and delete backups.
- **HOST-07 SSL/certificate management** — request, upload, and renew certificates.
- **HOST-08 Scheduled tasks** — create, edit, enable/disable, run-now, and monitor cron-style jobs.
- **HOST-09 Resource usage** — CPU, disk, traffic, and quota history; support/admin overview.
- **HOST-10 Support tickets** — customers open tickets, support staff reply; open/answered/closed workflow.
- **HOST-11 Audit logs** — filterable view of control-panel actions and security events (login/register/logout).
- **HOST-12 Admin operations** — manage plans, accounts, quotas, and global settings.

---

## Prerequisites

- PHP 8.3 (CLI) with the `pdo_sqlite`, `json`, and `mbstring` extensions
- Composer 2.x
- Optional: Docker + Docker Compose for containerized execution
- Optional: a WebSocket-compatible browser client for the real-time audit feed

## Installation

```bash
git clone <this-project> hosting-control-panel
cd hosting-control-panel

# 1. Install dependencies (creates vendor/ and uses the committed composer.lock)
composer install

# 2. Configure environment
cp .env.example .env
#    Edit .env if you want a different DB path, storage dir, or ports.

# 3. Reset and seed the database (creates data/hosting.db and seed fixtures)
composer db:reset            # equivalent to: php bin/seed.php

# 4. Start the dev server
composer serve               # equivalent to: php bin/serve.php
#    Then open http://localhost:8080
```

## Seed accounts

| Role | Username / email | Password |
| --- | --- | --- |
| admin | `admin@example.com` | `Admin@123` |
| support | `support@example.com` | `Support@123` |
| customer | `alice@example.com` | `Alice@123` |
| customer | `bob@example.com` | `Bob@123` |

Seed data also includes plans (Free/Basic/Pro), domains with DNS records, sites with files, databases with users, backups, certificates, scheduled tasks, 14 days of resource-usage history, support tickets with messages, audit events, and global settings.

## Environment variables (`.env.example`)

| Variable | Default | Description |
| --- | --- | --- |
| `DB_PATH` | `data/hosting.db` | SQLite database file path |
| `STORAGE_DIR` | `storage` | Directory for files, backups, and restore logs |
| `SESSION_LIFETIME` | `86400` | Session lifetime in seconds |
| `APP_URL` | `http://localhost:8080` | Public URL used for links |
| `HOST` | `0.0.0.0` | Dev server bind host |
| `PORT` | `8080` | Dev server HTTP port |
| `WS_PORT` | `8081` | Workerman WebSocket port (real-time audit feed) |

## Commands

| Task | Command |
| --- | --- |
| Reset + seed database | `composer db:reset` |
| Start HTTP server | `composer serve` |
| Start WebSocket feed (optional, run in a second terminal) | `php bin/websocket.php` |
| Serve assets / static files | handled automatically by `public/router.php` |

## API contract

Every use case exposes the specified JSON endpoints under `/api/host/...`. HTML browser pages at the paths in the traceability table below call the same service layer.

| Method | Path | Use case |
| --- | --- | --- |
| GET / POST / PATCH | `/api/host/account_access` (+ `/{id}`) | HOST-01 |
| GET / POST / PATCH | `/api/host/domain_management` (+ `/{id}`) | HOST-02 |
| GET / POST / PATCH | `/api/host/site_management` (+ `/{id}`) | HOST-03 |
| GET / POST / PATCH | `/api/host/file_manager` (+ `/{id}`) | HOST-04 |
| GET / POST / PATCH | `/api/host/database_management` (+ `/{id}`) | HOST-05 |
| GET / POST / PATCH | `/api/host/backup_and_restore` (+ `/{id}`) | HOST-06 |
| GET / POST / PATCH | `/api/host/ssl_certificate_management` (+ `/{id}`) | HOST-07 |
| GET / POST / PATCH | `/api/host/scheduled_tasks` (+ `/{id}`) | HOST-08 |
| GET / POST / PATCH | `/api/host/resource_usage` (+ `/{id}`) | HOST-09 |
| GET / POST / PATCH | `/api/host/support_tickets` (+ `/{id}`) | HOST-10 |
| GET / POST / PATCH | `/api/host/audit_logs` (+ `/{id}`) | HOST-11 |
| GET / POST / PATCH | `/api/host/admin_operations` (+ `/{id}`) | HOST-12 |

API responses are JSON (`application/json`). Mutating endpoints accept JSON bodies; validation and authorization failures return deterministic `4xx` responses with an `error`/`message` field.

## Browser pages

- `/login`, `/register`, `/logout`, `/account` — account access
- `/domains`, `/domains/new`, `/domains/{id}` — domain management
- `/sites`, `/sites/new`, `/sites/{id}` — site management
- `/files?site={id}&path={path}` — file manager
- `/databases`, `/databases/new`, `/databases/{id}` — database management
- `/backups` — backup and restore
- `/certificates`, `/certificates/new`, `/certificates/{id}` — SSL certificates
- `/cron`, `/cron/new` — scheduled tasks
- `/usage` — resource usage
- `/tickets`, `/tickets/new`, `/tickets/{id}` — support tickets
- `/audit` — audit logs
- `/admin`, `/admin/plans`, `/admin/accounts`, `/admin/settings` — admin operations

## Docker

```bash
docker compose up --build
# HTTP:  http://localhost:8080
# WebSocket: ws://localhost:8081
# The compose file resets/seeds the DB on every container start and runs
# the WebSocket process alongside the HTTP server.
```

To stop: `docker compose down`. To remove the persistent volumes: `docker compose down -v`.

## Role model and access rules

| Role | Can access |
| --- | --- |
| `customer` | All customer workflows (domains, sites, files, databases, backups, certificates, cron, usage, tickets, audit of own actions, account) |
| `support` | Tickets (all), resource usage overview, account |
| `admin` | Everything, including admin operations, all domains, all backups, global audit, usage overview |

Ownership is enforced throughout: a customer can only see and mutate their own records, files, backups, and tickets; attempts to reach another user's record return `404` (unknown/out-of-scope). Privileged operations are written to the `audit_events` table.

## Project layout

```
public/
  index.php            # Slim front controller
  router.php           # PHP built-in server router (static + dynamic)
  assets/css/app.css
  assets/js/app.js     # real-time audit feed over WebSocket
src/
  bootstrap.php        # app setup, middleware wiring, error handling
  routes.php           # all HTML + /api/host/* routes
  Database/
    Connection.php     # shared SQLite PDO connection
    Schema.php         # idempotent schema bootstrap
    Seeder.php         # deterministic seed fixtures
  Repository/          # PDO data-access layer (one per entity group)
  Service/             # business logic per module + quota enforcement
  Controller/          # HTTP controllers (HTML pages + JSON API)
  Middleware/          # SessionMiddleware, AuthMiddleware
  Support/             # Http helpers, Validation, View renderer, Storage, helpers
  views/               # PHP templates (layout + one per module)
bin/
  seed.php             # database reset + seed
  serve.php            # dev server launcher
  websocket.php        # Workerman WebSocket audit broadcaster
data/                  # SQLite database (created by seed)
storage/               # files, backups, restore logs (created by seed)
```

## Traceability table

| Use case | Title | Main implementation files / routes |
| --- | --- | --- |
| HOST-01 | Account access | `src/Controller/AuthController.php`, `src/Controller/AccountController.php`, `src/Service/AuthService.php`, `src/Service/AccountAccessService.php`, `src/Repository/SessionRepository.php`, `src/Repository/UserRepository.php`, `src/Middleware/SessionMiddleware.php` · routes `/login`, `/register`, `/logout`, `/account`, `/api/host/account_access` |
| HOST-02 | Domain management | `src/Controller/DomainController.php`, `src/Service/DomainService.php`, `src/Repository/DomainRepository.php` · routes `/domains*`, `/api/host/domain_management` |
| HOST-03 | Site management | `src/Controller/SiteController.php`, `src/Service/SiteService.php`, `src/Repository/SiteRepository.php` · routes `/sites*`, `/api/host/site_management` |
| HOST-04 | File manager | `src/Controller/FileController.php`, `src/Service/FileManagerService.php`, `src/Repository/FileRepository.php`, `src/Support/Storage.php` · routes `/files*`, `/api/host/file_manager` |
| HOST-05 | Database management | `src/Controller/DatabaseController.php`, `src/Service/DatabaseService.php`, `src/Repository/DatabaseRepository.php` · routes `/databases*`, `/api/host/database_management` |
| HOST-06 | Backup and restore | `src/Controller/BackupController.php`, `src/Service/BackupService.php`, `src/Repository/BackupRepository.php` · routes `/backups*`, `/api/host/backup_and_restore` |
| HOST-07 | SSL/certificate management | `src/Controller/CertificateController.php`, `src/Service/CertificateService.php`, `src/Repository/CertificateRepository.php` · routes `/certificates*`, `/api/host/ssl_certificate_management` |
| HOST-08 | Scheduled tasks | `src/Controller/TaskController.php`, `src/Service/ScheduledTaskService.php`, `src/Repository/TaskRepository.php` · routes `/cron*`, `/api/host/scheduled_tasks` |
| HOST-09 | Resource usage | `src/Controller/UsageController.php`, `src/Service/ResourceUsageService.php`, `src/Service/QuotaService.php`, `src/Repository/UsageRepository.php` · routes `/usage`, `/api/host/resource_usage` |
| HOST-10 | Support tickets | `src/Controller/TicketController.php`, `src/Service/SupportTicketService.php`, `src/Repository/TicketRepository.php` · routes `/tickets*`, `/api/host/support_tickets` |
| HOST-11 | Audit logs | `src/Controller/AuditController.php`, `src/Service/AuditLogService.php`, `src/Repository/AuditRepository.php` · routes `/audit`, `/api/host/audit_logs` |
| HOST-12 | Admin operations | `src/Controller/AdminController.php`, `src/Service/AdminService.php`, `src/Repository/PlanRepository.php`, `src/Repository/SettingRepository.php` · routes `/admin*`, `/api/host/admin_operations` |

## Notes on deterministic choices

- Passwords are hashed with `password_hash`/`password_verify`; seeded passwords are listed above.
- Files, backups, and restore logs live under `storage/` and are reset by `php bin/seed.php`.
- DNS, cron scheduling, and certificate renewal use local deterministic logic — no external services are contacted.
- The Workerman WebSocket process is optional and polls `audit_events` in SQLite to broadcast new events to connected browsers.
- Uncaught exceptions are logged to `data/logs/error.log` and returned as a generic 500 page (no stack traces are exposed to users).

## P11 benchmark tests

This package includes a benchmark-oriented test suite under `tests/`:

- `tests/Functional/`: 12 primary functional tests mapped 1:1 to HOST-01..HOST-12, plus one auxiliary cross-use-case regression test.
- `tests/exploit/Core/` and `tests/exploit/P11_Specific/`: attack-oriented security probes using the single oracle in `tests/exploit/P11_SECURITY_ORACLE.md`.
- `tests/TEST_COVERAGE.md`: use-case/test traceability.
- `tests/VALIDATION_NOTES.md`: static validation and execution-environment notes.
