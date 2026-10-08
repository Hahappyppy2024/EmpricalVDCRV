# P13 — Mail Server / Admin Console

A complete, runnable synthetic mail-server admin console (Roundcube Webmail
workflow-aligned benchmark) built with **PHP 8.3**, **Slim 4**, **SQLite**,
server-side persistent sessions, vanilla-JS browser pages and an optional
**Workerman** WebSocket notification process. No external services are used:
mail delivery is simulated with a deterministic local adapter and the
application runs fully offline after `composer install`.

---

## Tech stack (authoritative)

| Concern            | Choice                                                               |
|--------------------|----------------------------------------------------------------------|
| Language / runtime | PHP 8.3                                                              |
| Web framework      | Slim 4 (PSR-7 request/response)                                       |
| Database           | SQLite via PDO (repository/data-access layer in `src/Services/`)      |
| Authentication     | Server-side sessions in SQLite, HTTP-only cookie (`p13_session`)      |
| Browser client     | Server-served HTML + CSS + vanilla JavaScript                         |
| Real-time          | Optional Workerman process sharing the same SQLite file (`wss/`)      |
| Dependencies       | Composer, exact constraints in `composer.json`, committed lock        |
| Config             | `.env` documented in `.env.example`                                   |
| Containerization   | `Dockerfile` + `compose.yaml`                                         |

---

## Prerequisites

- PHP **8.3** CLI with extensions: `pdo`, `pdo_sqlite`, `json`, `mbstring` (or the
  bundled polyfills), `fileinfo`
- Composer 2.x
- Optional: Docker with Compose, for containerized execution

Check the local setup:

```bash
php -v
composer --version
php -m | findstr pdo_sqlite   # Linux/macOS: php -m | grep pdo_sqlite
```

## Setup

```bash
# 1. Install dependencies (generates vendor/ + composer.lock)
composer install

# 2. Configure environment
copy .env.example .env        # Windows
# cp .env.example .env        # Linux/macOS

# 3. Reset + seed the database (wipes data, applies deterministic fixtures)
php scripts/seed.php          # or: composer db:seed
```

## Seed accounts

Every seed account uses the password `Passw0rd!` (configurable via `SEED_PASSWORD`).

| Account            | Role          | Domain        | Purpose                          |
|--------------------|---------------|---------------|----------------------------------|
| `alice`            | mail_user     | example.com   | Mailbox workflows                |
| `bob`              | mail_user     | example.com   | Second mailbox                   |
| `carol`            | mail_user     | corporate.org | Second-domain mailbox            |
| `dan`              | domain_admin  | example.com   | Domain management / quarantine   |
| `eva`              | domain_admin  | corporate.org | Second-domain admin              |
| `sysadmin`         | system_admin  | —             | Audit logs                       |

Seed data includes: 2 domains, 6 accounts, folders, threaded + unread
messages, stored attachment files, contacts, mailbox rules, quarantine
items, audit events, access history and import/export jobs.

## Startup

```bash
# Development server (with static-file router)
php -S 127.0.0.1:8080 -t public public/router.php
# or: composer serve
```

Then open <http://127.0.0.1:8080> and sign in with `alice` / `Passw0rd!`.

### Optional real-time WebSocket process

```bash
php wss/server.php start      # or: composer ws
# WS_PORT defaults to 8090 (see .env). Works on Linux; on Windows it runs in
# single-process development mode. The browser falls back to plain HTTP.
```

## Database commands

```bash
composer db:reset      # alias for php scripts/seed.php
composer db:seed       # same — reset + deterministic seed fixtures
```

The database file is `./data/p13.sqlite` (override with `DATABASE_PATH`).
Uploaded/generated files live under `./storage/uploads`.

## Docker

```bash
docker compose up --build          # web app on http://127.0.0.1:8080
docker compose up ws               # optional Workerman WebSocket process (port 8090)
docker compose down                # stop
```

## Smoke checks

With the app running on `http://127.0.0.1:8080`:

```bash
php scripts/smoke.php              # or: composer smoke
```

The smoke script exercises authentication, role boundaries, and one workflow
per module through the real HTTP API.

---

## HTTP API contract

All module APIs are authenticated (401 without a session), CSRF-protected on
state-changing methods, and JSON:

| Module                         | GET                      | POST                      | PATCH                             |
|--------------------------------|--------------------------|---------------------------|-----------------------------------|
| account_access                 | `/api/mail/account_access` | `/api/mail/account_access` | `/api/mail/account_access/{id}`   |
| mailbox_overview               | `/api/mail/mailbox_overview` | `/api/mail/mailbox_overview` | `/api/mail/mailbox_overview/{id}` |
| message_compose                | `/api/mail/message_compose` | `/api/mail/message_compose` | `/api/mail/message_compose/{id}`  |
| message_reading                | `/api/mail/message_reading` (+ `/{id}`) | `/api/mail/message_reading` | `/api/mail/message_reading/{id}`  |
| attachment_handling            | `/api/mail/attachment_handling` | `/api/mail/attachment_handling` | `/api/mail/attachment_handling/{id}` (+ `/{id}/download`) |
| contact_management             | `/api/mail/contact_management` | `/api/mail/contact_management` | `/api/mail/contact_management/{id}` |
| filters_and_rules              | `/api/mail/filters_and_rules` | `/api/mail/filters_and_rules` | `/api/mail/filters_and_rules/{id}` |
| domain_management (admin)      | `/api/mail/domain_management` | `/api/mail/domain_management` | `/api/mail/domain_management/{id}` |
| quarantine (admin)             | `/api/mail/quarantine` | `/api/mail/quarantine` | `/api/mail/quarantine/{id}`       |
| admin_audit_logs (sysadmin)    | `/api/mail/admin_audit_logs` | `/api/mail/admin_audit_logs` | `/api/mail/admin_audit_logs/{id}` |
| import_export                  | `/api/mail/import_export` | `/api/mail/import_export` | `/api/mail/import_export/{id}`    |
| frontend_api_integration_and_errors | `/api/mail/frontend_api_integration_and_errors` | `/api/mail/frontend_api_integration_and_errors` | `/api/mail/frontend_api_integration_and_errors/{id}` |

Auth: `GET /api/health`, `GET /api/auth/me`, `POST /api/auth/login`,
`POST /api/auth/register`, `POST /api/auth/logout`.

Browser pages: `/login`, `/register`, `/dashboard`, `/account`, `/mailbox`,
`/compose`, `/message/{id}`, `/attachments`, `/contacts`, `/rules`,
`/import-export`, `/errors`, `/domains`, `/quarantine`, `/audit`.

## Security behavior

- User-visible errors are deterministic and never leak stack traces.
- Ownership is enforced on every read/write (cross-user access → 404/403).
- Role middleware enforces admin boundaries (mail_user → 403 on admin APIs).
- Persistent sessions are stored in SQLite; sign-out expires the record.
- CSRF tokens are validated on every state-changing request.
- Uploads are type/size-checked and stored with owner metadata.

## Directory layout

```
public/        front controller, static assets (CSS, JS)
src/           App bootstrap, routes, middleware, controllers, services, schema
templates/     server-rendered pages + shared layout
scripts/       seed.php (db reset), smoke.php (HTTP checks)
wss/           optional Workerman WebSocket notification server
data/          SQLite database (created at seed)
storage/       local file storage (uploads)
```

## Use-case traceability

| Use case | Title | Main implementation (routes / files) |
|----------|-------|--------------------------------------|
| MAIL-01  | Account access | `POST /api/auth/login`, `POST /api/auth/register`, `POST /logout`, `GET/POST/PATCH /api/mail/account_access`, `src/Auth.php`, `src/Session.php`, `templates/auth/*`, `templates/account.php` |
| MAIL-02  | Mailbox overview | `GET/POST/PATCH /api/mail/mailbox_overview`, `src/Services/MailboxService.php`, `templates/mailbox.php` |
| MAIL-03  | Message compose | `GET/POST/PATCH /api/mail/message_compose`, `src/Services/ComposeService.php`, `templates/compose.php` |
| MAIL-04  | Message reading | `GET/POST/PATCH /api/mail/message_reading` (+ `GET {id}`), `src/Services/ReadingService.php`, `templates/message.php` |
| MAIL-05  | Attachment handling | `GET/POST/PATCH /api/mail/attachment_handling` (+ `GET {id}/download`), `src/Services/AttachmentService.php`, `src/Storage.php`, `templates/attachments.php` |
| MAIL-06  | Contact management | `GET/POST/PATCH /api/mail/contact_management`, `src/Services/ContactService.php`, `templates/contacts.php` |
| MAIL-07  | Filters and rules | `GET/POST/PATCH /api/mail/filters_and_rules`, `src/Services/RuleService.php`, `templates/rules.php` |
| MAIL-08  | Domain management | `GET/POST/PATCH /api/mail/domain_management` (domain_admin/system_admin), `src/Services/DomainService.php`, `templates/domains.php` |
| MAIL-09  | Quarantine | `GET/POST/PATCH /api/mail/quarantine` (domain_admin/system_admin), `src/Services/QuarantineService.php`, `templates/quarantine.php` |
| MAIL-10  | Admin audit logs | `GET/POST/PATCH /api/mail/admin_audit_logs` (system_admin), `src/Audit.php`, `templates/audit.php` |
| MAIL-11  | Import/export | `GET/POST/PATCH /api/mail/import_export`, `src/Services/ImportExportService.php`, `templates/import_export.php` |
| MAIL-12  | Frontend API integration and errors | `GET/POST/PATCH /api/mail/frontend_api_integration_and_errors`, `src/Services/FrontendApiService.php`, `templates/errors.php`, `public/assets/js/api.js` |

## Design decisions (documented choices for unspecified details)

- **Simulated delivery**: sending a message inserts a copy into the recipient's
  Inbox and the sender's Sent folder (no SMTP). Unknown/invalid recipients are
  rejected with a stable 422 error.
- **Guest CSRF**: the login/register pages issue a `p13_csrf` cookie; the token
  is submitted via the `_csrf` form field or the `X-CSRF-Token` header.
- **Domain-admin scope**: domain admins only manage the domain they belong to;
  system admins manage all domains and see all quarantine items.
- **Threads**: seed messages share a `thread_id`; new sends are standalone.
- **CSV formats**: contacts export/import use
  `email,first_name,last_name,phone,organization`.
