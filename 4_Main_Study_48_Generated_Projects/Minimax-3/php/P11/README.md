# P11 — AetherPanel Hosting Control Panel

A complete, runnable PHP/Slim 4 hosting control panel aligned with the workflow anchors
of **Froxlor**: domains, web spaces, file manager, databases, certificates, backups,
cron jobs, support tickets, quotas, and admin operations.

The project covers every use case listed in `P11_Hosting_Control_Panel` (HOST-01
through HOST-12). It uses SQLite for persistence, server-side sessions stored in the
database, and a deterministic seed dataset.

## Stack

| Concern | Choice |
| --- | --- |
| Language / runtime | PHP 8.3 |
| Web framework | Slim 4 with PSR-7 |
| Database | SQLite via PDO |
| Authentication | HTTP-only session cookie + opaque session row in SQLite |
| Browser client | Server-served HTML, CSS, vanilla JS |
| Dependency tooling | Composer (`composer.lock` committed) |
| Container | PHP-DI 7 |
| Containerization | Docker / docker-compose |

No external services are required. The app runs offline once dependencies are installed.

## Project layout

```
P11/
├── composer.json / composer.lock
├── .env.example
├── Dockerfile / docker-compose.yml
├── README.md
├── public/                  ← web root (index.php router + assets)
│   ├── index.php
│   └── assets/{style.css,app.js}
├── src/
│   ├── Bootstrap.php        ← Slim app wiring
│   ├── App.php-equivalent   ← see Bootstrap.php
│   ├── Database.php
│   ├── Config.php
│   ├── View.php
│   ├── Auth/SessionService.php
│   ├── Middleware/{AuthMiddleware,RoleMiddleware,CsrfMiddleware}.php
│   ├── Controllers/         ← 13 controllers (one per use case + dashboard/account)
│   ├── Repositories/         ← 13 PDO-backed data-access classes
│   ├── Services/{AuditLogger,Validator,Flash}.php
│   └── Views/               ← plain-PHP templates, organised by use case
├── db/
│   ├── schema.sql            ← full SQLite schema (19 tables)
│   └── seed.sql              ← deterministic seed data
├── bin/
│   ├── init-db.php           ← database reset / seed command
│   ├── smoke_runner.php      ← curl-based smoke test
│   ├── _wait.php             ← helper: wait until a TCP port is open
│   └── smoke.bat             ← Windows wrapper that starts/stops server
└── storage/                  ← data/uploads/backups (writable)
```

## Prerequisites

- PHP 8.3+ with extensions `pdo`, `pdo_sqlite`, `json`
- Composer 2

## Setup

```bash
# 1. install dependencies (uses committed composer.lock for reproducibility)
composer install --no-interaction --prefer-dist

# 2. copy environment defaults (optional; the app boots with built-in defaults)
copy .env.example .env

# 3. initialise / reset the SQLite database with deterministic seed data
php bin/init-db.php
```

The init script prints the seeded accounts:

| Username | Role | Password |
| --- | --- | --- |
| `admin` | admin | `Password123!` |
| `support` | support | `Password123!` |
| `alice` | customer (Pro plan) | `Password123!` |
| `bob` | customer (Starter plan) | `Password123!` |
| `charlie` | customer (Business plan) | `Password123!` |

## Start the dev server

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Open `http://localhost:8080/login` in a browser and sign in as `alice / Password123!`
or `admin / Password123!`.

## Smoke test

The smoke runner exercises every use case against a live server:

```bat
bin\smoke.bat
```

On a non-Windows host:

```bash
php -S 127.0.0.1:8484 -t public public/index.php &
php bin/smoke_runner.php http://127.0.0.1:8484
```

Expected result: `25 / 25 tests passed`.

## Docker

```bash
docker compose build
docker compose up -d
# browse to http://localhost:8080
```

The container runs `bin/init-db.php` before launching the PHP server. The local
`storage/` directory is bind-mounted for data persistence.

## Environment variables

See `.env.example`. The most relevant ones:

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_ENV` | `local` | `local` or `production` |
| `APP_DEBUG` | `true` | Reveals stack traces on 500 errors |
| `DB_PATH` | `storage/data/panel.sqlite` | SQLite file location (relative to project root unless absolute) |
| `UPLOAD_DIR` | `storage/uploads` | Where uploaded files land |
| `BACKUP_DIR` | `storage/backups` | Where backup markers/archives go |
| `SESSION_NAME` | `panel_session` | PHP session cookie name |
| `SESSION_LIFETIME` | `86400` | Session lifetime in seconds |
| `APP_SECRET` | `please-change-me` | Reserved for future HMAC signing |

## API surface (host integration)

Every use case is exposed twice:

1. Browser-friendly HTML pages under `/<resource>` and `/<resource>/{id}`.
2. JSON-only API under `/api/host/<resource>` (e.g. `/api/host/domain_management`).

Both honour the same business rules and use the same controllers.

## Traceability — use cases ↔ files

| Use case | Controllers | Routes | Views / templates |
| --- | --- | --- | --- |
| HOST-01 Account access | `src/Controllers/AuthController.php`, `src/Controllers/AccountController.php` | `GET/POST /login`, `GET/POST /register`, `POST /logout`, `GET /account`, `POST /account/password`, `GET/POST/PATCH /api/host/account_access[/{id}]` | `src/Views/auth/login.php`, `src/Views/auth/register.php`, `src/Views/account.php`, `src/Views/dashboard.php` |
| HOST-02 Domain management | `src/Controllers/DomainController.php` | `GET/POST/PATCH/DELETE /domains[/{id}]`, `POST /domains/{id}/records`, `DELETE /domains/{id}/records/{rid}`, `GET/POST/PATCH/DELETE /api/host/domain_management[/{id}]` | `src/Views/domains.php`, `src/Views/domains_show.php` |
| HOST-03 Site management | `src/Controllers/SiteController.php` | `GET/POST/PATCH/DELETE /sites[/{id}]`, `GET/POST/PATCH/DELETE /api/host/site_management[/{id}]` | `src/Views/sites.php`, `src/Views/sites_show.php` |
| HOST-04 File manager | `src/Controllers/FileManagerController.php` | `GET/POST/PATCH/DELETE /files[/{id}]`, `GET/POST/PATCH/DELETE /api/host/file_manager[/{id}]` | `src/Views/files.php` |
| HOST-05 Database management | `src/Controllers/DatabaseManagementController.php` | `GET/POST/PATCH/DELETE /databases[/{id}]`, `GET/POST/PATCH/DELETE /api/host/database_management[/{id}]` | `src/Views/databases.php` |
| HOST-06 Backup and restore | `src/Controllers/BackupController.php` | `GET/POST /backups`, `POST /backups/upload`, `POST/DELETE /backups/{id}/...`, `GET/POST/DELETE /api/host/backup_and_restore[/{id}]` | `src/Views/backups.php` |
| HOST-07 SSL/certificate management | `src/Controllers/SslController.php` | `GET/POST /ssl`, `POST /ssl/{id}/renew|revoke`, `GET/POST /api/host/ssl_certificate_management[/{id}]/...` | `src/Views/ssl.php` |
| HOST-08 Scheduled tasks | `src/Controllers/CronController.php` | `GET/POST/PATCH/DELETE /cron[/{id}]`, `POST /cron/{id}/run`, same under `/api/host/scheduled_tasks` | `src/Views/cron.php` |
| HOST-09 Resource usage | `src/Controllers/ResourceController.php` | `GET/POST /resources`, `PATCH /resources/{id}`, same under `/api/host/resource_usage` | `src/Views/resources.php` |
| HOST-10 Support tickets | `src/Controllers/TicketController.php` | `GET/POST /tickets`, `GET /tickets/{id}`, `POST /tickets/{id}/reply|status|assign`, same under `/api/host/support_tickets` | `src/Views/tickets.php`, `src/Views/tickets_show.php` |
| HOST-11 Audit logs | `src/Controllers/AuditController.php`, `src/Services/AuditLogger.php` | `GET /audit[/{id}]`, `POST /audit`, same under `/api/host/audit_logs` | `src/Views/audit.php` |
| HOST-12 Admin operations | `src/Controllers/AdminController.php` (admin role only) | `GET /admin`, `POST /admin/users`, `PATCH/POST /admin/users/{id}/update`, `POST /admin/settings`, same under `/api/host/admin_operations` | `src/Views/admin.php` |

Persistent entities per the spec are stored in SQLite (see `db/schema.sql`):

- `User`, `Session` (HOST-01)
- `Domain`, `DnsRecord`, `AuditEvent` (HOST-02)
- `Site`, `AuditEvent` (HOST-03)
- `File`, `StoredFile` (HOST-04 — file metadata + content on disk under `storage/uploads/{user}`)
- `Database` (HOST-05)
- `Backup`, `StoredFile` (HOST-06 — metadata + marker files under `storage/backups/{user}`)
- `Certificate` (HOST-07)
- `CronJob` (HOST-08)
- `ResourceUsage` (HOST-09)
- `Ticket`, `TicketReply` (HOST-10)
- `AuditEvent`, `AuditLogs` (HOST-11 — single `audit_events` table)
- `User`, `Session`, `AdminOperations`, `Setting`, `Plan` (HOST-12)

## Acceptance criteria coverage

| ID | Where verified |
| --- | --- |
| HOST-01-FA-01 valid sign-in → dashboard | `bin/smoke.bat` tests 1–3 |
| HOST-01-FA-02 invalid credentials rejected | AuthController branches for unknown user / wrong password / inactive |
| HOST-01-FA-03 sign-out removes access | `POST /logout` clears `$_SESSION` and deletes DB row |
| HOST-02-FA-01..03 domain CRUD + ownership + privilege | DomainController enforces ownership; admin can edit anything |
| HOST-03-FA-01..03 site CRUD + ownership + privilege | SiteController, smoke test 6 |
| HOST-04-FA-01..03 file upload/rename/delete + ownership | FileManagerController, smoke test 7 |
| HOST-05-FA-01..03 database create/update/delete + privilege | DatabaseManagementController, smoke test 8 |
| HOST-06-FA-01..03 backup create/upload/restore + ownership | BackupController, smoke test 9 |
| HOST-07-FA-01..03 certificate install/renew/revoke + ownership | SslController, smoke test 10 |
| HOST-08-FA-01..03 cron create/run/delete | CronController, smoke test 11 |
| HOST-09-FA-01..03 resource usage own/filter | ResourceController, smoke test 12 |
| HOST-10-FA-01..03 ticket CRUD + role-based replies | TicketController, smoke tests 13, 22–24 |
| HOST-11-FA-01..03 audit log filter + empty/error + private records | AuditController filters, smoke test 14 |
| HOST-12-FA-01..03 admin CRUD + privilege | AdminController + RoleMiddleware on `/admin` and `/api/host/admin_operations`, smoke tests 15, 18 |

## Notes on determinism

- All seed passwords are the literal string `Password123!`. The bcrypt hash is generated
  at `php bin/init-db.php` time and substituted into `db/seed.sql` via a single
  `str_replace`, so the dataset is reproducible across machines without sharing
  bcrypt salts.
- Uploaded files are stored on disk under `storage/uploads/{user_id}/` so that file
  metadata in the `files` table can be linked back to the owning user and the bytes
  remain available for download.
- Backups write a marker file under `storage/backups/{user_id}/` with the same name as
  the row in `backups`, giving the panel a verifiable target without requiring an
  external archive toolchain.

## License

MIT — synthetic benchmark project, not derived from any real-world product.