# P13 — Mail Server / Admin Console

A complete, runnable PHP 8.3 + Slim 4 + SQLite web application that mirrors the
high-level workflow categories of a Roundcube-style mail server console:
**mailbox folders, compose, message reading, attachments, contacts, filters,
identities (via sender/recipient metadata), search, imports/exports, and admin
logs / quarantine**.

> Synthetic benchmark project. Not a clone of Roundcube. CVE details are not
> copied into this codebase; the implementation is independent and uses
> deterministic local adapters only.

---

## 1. Prerequisites

| Tool         | Version (tested)         |
|--------------|--------------------------|
| PHP          | **8.3** (CLI)            |
| Composer     | 2.x                      |
| PHP exts     | `pdo`, `pdo_sqlite`, `json`, `fileinfo`, `mbstring`, `ctype` |
| Optional     | Docker 24+, Docker Compose v2 |

No external services, no paid accounts. Everything is offline once
`composer install` has finished.

---

## 2. Setup

### 2.1 Install PHP dependencies

```bash
composer install --no-interaction --no-security-blocking
```

(`--no-security-blocking` is only needed if a transitive dependency triggers a
historical advisory in your local advisory DB; this build was generated with
the lock file pinned to exact versions.)

### 2.2 Configure environment (optional)

Copy `.env.example` to `.env` and adjust the values if needed:

```bash
cp .env.example .env
```

Defaults:

```
APP_DB_PATH=storage/app.db
APP_UPLOAD_DIR=storage/uploads
APP_EXPORT_DIR=storage/exports
APP_HOST=127.0.0.1
APP_PORT=8080
```

### 2.3 Initialize / reset the SQLite database

```bash
php bin/init-db.php     # first-time setup (drops any existing DB, runs migrations, seeds)
php bin/reset-db.php    # equivalent alias for re-seeding
```

The migrations script creates every table required by the use cases. The seed
script inserts deterministic fixtures:

- 2 domains (`example.com`, `acme.test`) and 3 aliases.
- 5 users covering all roles (`alice`, `bob`, `carol` are mail users;
  `dadm` is a domain admin; `sadm` is a system admin).
- Default folders (`Inbox`, `Sent`, `Drafts`, `Trash`, `Spam`, `Archive`).
- 6 seed messages, 8 contacts, 3 filters, 3 quarantine entries, 6 audit
  events, 4 API errors.

---

## 3. Seed accounts (all share password `Password123!`)

| Username | Role          | Notes                                |
|----------|---------------|--------------------------------------|
| alice    | mail_user     | Has seeded Inbox/Sent/folders        |
| bob      | mail_user     | Cross-mailbox tests                  |
| carol    | mail_user     | Belongs to `acme.test`               |
| dadm     | domain_admin  | Manages `example.com`                |
| sadm     | system_admin  | Sees audit logs and all domains      |

---

## 4. Run the application

### 4.1 PHP built-in server (recommended for local development)

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open <http://127.0.0.1:8080> in a browser. You will be redirected to
`/login`.

On Windows PowerShell, if you need to keep the server alive from a script
session, wrap it in `Start-Job`:

```powershell
$job = Start-Job -ScriptBlock {
  Set-Location 'D:\path\to\project'
  php -S 127.0.0.1:8080 -t public public/index.php
}
```

### 4.2 Docker / docker compose

```bash
docker compose up --build -d
# Browse http://127.0.0.1:8080
docker compose logs -f app
docker compose down
```

The `Dockerfile` installs PHP 8.3 with `pdo_sqlite`, runs
`composer install`, seeds the database, and starts the same built-in server.

### 4.3 Standalone Docker build

```bash
docker build -t mail-server-admin-console .
docker run --rm -p 8080:8080 -v "${PWD}/storage:/app/storage" mail-server-admin-console
```

---

## 5. Database commands

| Action                | Command                  |
|-----------------------|--------------------------|
| Initialize (migrate + seed) | `php bin/init-db.php` |
| Re-seed (drop + recreate)   | `php bin/reset-db.php` |
| Inspect schema              | `sqlite3 storage/app.db ".schema"` |
| Inspect tables              | `sqlite3 storage/app.db ".tables"` |

---

## 6. Smoke check

### 6.1 In-process smoke test (no HTTP server required)

Exercises key routes and use cases directly through the Slim app:

```bash
php bin/smoke-test.php
```

### 6.2 End-to-end HTTP smoke test (against a running server)

Exercises every use case via real HTTP requests including multipart uploads
and PATCH operations. Start the server first (see section 4), then in another
shell run:

```bash
php bin/e2e-smoke.php 8080
```

Latest run on this build (38 / 38 passed):

```
PASS MAIL-01 health                                          expected=200 actual=200
PASS MAIL-01 login page renders                              expected=200 actual=200
PASS MAIL-01 dashboard unauth redirects                      expected=302 actual=302
PASS MAIL-01 dashboard unauth location                       expected=/login actual=/login
PASS MAIL-01 API unauth -> 401                               expected=401 actual=401
PASS MAIL-01 login good -> 302 /dashboard                    expected=302//dashboard actual=302//dashboard
PASS MAIL-01 MSAC_SID cookie issued                          expected=yes actual=yes
PASS MAIL-01 login bad -> 401                                expected=401 actual=401
PASS MAIL-01 logout -> 302                                   expected=302 actual=302
PASS MAIL-01 after logout dashboard redirects                expected=302 actual=302
PASS MAIL-02 mailbox page renders                            expected=200 actual=200
PASS MAIL-02 mailbox overview api                            expected=200 actual=200
PASS MAIL-02 create folder                                   expected=200 actual=200
PASS MAIL-03 compose page renders                            expected=200 actual=200
PASS MAIL-03 send message                                    expected=201 actual=201
PASS MAIL-03 invalid recipient -> 422                        expected=422 actual=422
PASS MAIL-04 message page renders                            expected=200 actual=200
PASS MAIL-04 read message api                                expected=200 actual=200
PASS MAIL-04 missing -> 404                                  expected=404 actual=404
PASS MAIL-04 star action                                     expected=200 actual=200
PASS MAIL-05 upload                                          expected=201 actual=201
PASS MAIL-05 download                                        expected=200 actual=200
PASS MAIL-05 upload .bin ok                                  expected=201 actual=201
PASS MAIL-06 add contact                                     expected=201 actual=201
PASS MAIL-06 edit contact                                    expected=200 actual=200
PASS MAIL-06 list contacts                                   expected=200 actual=200
PASS MAIL-07 add rule                                        expected=201 actual=201
PASS MAIL-11 import contacts (302)                           expected=302 actual=302
PASS MAIL-11 export contacts                                 expected=200 actual=200
PASS MAIL-12 log api error                                   expected=201 actual=201
PASS MAIL-08 list domains as dadm                            expected=200 actual=200
PASS MAIL-08 domains page renders                            expected=200 actual=200
PASS MAIL-09 list quarantine as dadm                        expected=200 actual=200
PASS MAIL-09 release quarantine item                         expected=200 actual=200
PASS MAIL-09 mail_user quarantine -> 403                     expected=403 actual=403
PASS MAIL-10 list audit as sadm                              expected=200 actual=200
PASS MAIL-10 audit page renders                              expected=200 actual=200
PASS MAIL-10 mail_user audit -> 403                          expected=403 actual=403
```

For a quick connectivity check, `http-smoke.php` exercises login + a single
mailbox round-trip:

```bash
php bin/http-smoke.php 8080
```

---

## 7. Use-case traceability table

Each row maps the use case ID from the prompt to its primary implementation
files and HTTP routes. The persistent entities listed match the contract.

| Use case | Title | Primary actors | HTTP routes (pages + API) | Persistent entities | Main source files |
|----------|-------|----------------|---------------------------|---------------------|-------------------|
| MAIL-01 | Account access | Mail user, domain admin, system admin | `GET /login`, `POST /login`, `GET /register`, `POST /register`, `any /logout`, `GET /dashboard`; `GET/POST /api/mail/account_access`, `PATCH /api/mail/account_access/{id}` | `User`, `Session`, `AccountAccess` | `src/Controllers/AccountAccessController.php`, `src/Auth/SessionService.php`, `src/Auth/AuthMiddleware.php`, `views/login.php`, `views/register.php`, `views/dashboard.php` |
| MAIL-02 | Mailbox overview | Mail user | `GET /mail`; `GET/POST /api/mail/mailbox_overview`, `PATCH /api/mail/mailbox_overview/{id}` | `User`, `Session`, `Folder`, `Message`, `MailboxOverview` (folder/message tables) | `src/Controllers/MailboxOverviewController.php`, `src/Repositories/MailboxRepository.php`, `views/mailbox.php` |
| MAIL-03 | Message compose | Mail user | `GET /mail/compose`, `POST /mail/compose`; `GET/POST /api/mail/message_compose`, `PATCH /api/mail/message_compose/{id}` | `User`, `Session`, `Message`, `Draft`, `MessageCompose` | `src/Controllers/MessageComposeController.php`, `src/Repositories/MessageRepository.php`, `views/compose.php` |
| MAIL-04 | Message reading | Mail user | `GET /mail/message/{id}`; `GET/POST /api/mail/message_reading`, `PATCH /api/mail/message_reading/{id}` | `User`, `Session`, `Message`, `MessageReading` | `src/Controllers/MessageReadingController.php`, `views/message.php` |
| MAIL-05 | Attachment handling | Mail user | `GET /mail/attachments`, `POST /mail/attachments`, `GET /mail/attachments/{id}/download`; `GET/POST /api/mail/attachment_handling`, `PATCH /api/mail/attachment_handling/{id}` | `User`, `Session`, `Attachment`, `StoredFile`, `AttachmentHandling` | `src/Controllers/AttachmentHandlingController.php`, `src/Repositories/FileRepository.php`, `views/attachments.php` |
| MAIL-06 | Contact management | Mail user | `GET /mail/contacts`, `POST /mail/contacts`, `POST /mail/contacts/{id}/edit`, `POST /mail/contacts/{id}/delete`; `GET/POST /api/mail/contact_management`, `PATCH /api/mail/contact_management/{id}` | `User`, `Session`, `Contact`, `AuditEvent`, `ContactManagement` | `src/Controllers/ContactManagementController.php`, `src/Repositories/ContactRepository.php`, `views/contacts.php` |
| MAIL-07 | Filters and rules | Mail user | `GET /mail/rules`, `POST /mail/rules`, `POST /mail/rules/{id}/edit`, `POST /mail/rules/{id}/delete`; `GET/POST /api/mail/filters_and_rules`, `PATCH /api/mail/filters_and_rules/{id}` | `User`, `Session`, `Rule`, `FiltersAndRules` | `src/Controllers/FiltersAndRulesController.php`, `src/Repositories/RuleRepository.php`, `views/rules.php` |
| MAIL-08 | Domain management | Domain admin | `GET /mail/domains`, `POST /mail/domains`, `POST /mail/domains/{id}/edit`, `POST /mail/domains/{id}/aliases`; `GET /api/mail/domain_management/list`, `POST /api/mail/domain_management`, `PATCH /api/mail/domain_management/{id}` | `User`, `Session`, `Domain`, `Alias`, `AuditEvent`, `DomainManagement` | `src/Controllers/DomainManagementController.php`, `src/Repositories/DomainRepository.php`, `views/domains.php` |
| MAIL-09 | Quarantine | Domain admin | `GET /mail/quarantine`, `POST /mail/quarantine/{id}/resolve`; `GET/POST /api/mail/quarantine`, `PATCH /api/mail/quarantine/{id}` | `User`, `Session`, `Quarantine`, `AuditEvent` | `src/Controllers/QuarantineController.php`, `src/Repositories/QuarantineRepository.php`, `views/quarantine.php` |
| MAIL-10 | Admin audit logs | System admin | `GET /mail/audit`; `GET/POST /api/mail/admin_audit_logs`, `PATCH /api/mail/admin_audit_logs/{id}` | `User`, `Session`, `AuditEvent`, `AdminAuditLogs` | `src/Controllers/AdminAuditLogsController.php`, `src/Repositories/AuditRepository.php`, `views/audit.php` |
| MAIL-11 | Import/export | Mail user, admin | `GET /mail/import-export`, `POST /mail/import-export/import`, `GET /mail/import-export/export`, `GET /mail/import-export/{id}/download`; `GET/POST /api/mail/import_export`, `PATCH /api/mail/import_export/{id}` | `User`, `Session`, `ImportExport`, `StoredFile`, `Contact` (target), `AuditEvent` | `src/Controllers/ImportExportController.php`, `views/import_export.php` |
| MAIL-12 | Frontend API integration and errors | User | `GET /mail/api-errors`, `POST /mail/api-errors`; `GET/POST /api/mail/frontend_api_integration_and_errors`, `PATCH /api/mail/frontend_api_integration_and_errors/{id}` | `User`, `Session`, `ApiError`, `AuditEvent`, `FrontendApiIntegrationAndErrors` | `src/Controllers/FrontendApiIntegrationController.php`, `src/Repositories/ApiErrorRepository.php`, `views/errors.php` |

---

## 8. Implementation notes

- **Auth**: server-side sessions, HTTP-only cookie `MSAC_SID`, persisted in
  `sessions` table with 24h TTL. `AuthMiddleware` enforces role checks for
  domain-admin and system-admin endpoints; cross-user operations return
  `404 stable_error not_found_or_forbidden` to avoid leaking existence.
- **Roles**:
  - `mail_user` — owns a mailbox; uses compose, contacts, rules, attachments,
    import/export.
  - `domain_admin` — additionally manages domains, aliases, mailbox quotas,
    quarantine items for their domain.
  - `system_admin` — additionally sees all domains, audit logs, and can
    record new audit entries.
- **Email**: messages are stored in the `messages` table; outbound messages
  land in the user's `Sent` folder. There is no external SMTP/IMAP — delivery
  is deterministic and synchronous into SQLite, satisfying the “simulated
  email” requirement.
- **Attachments**: uploaded files are streamed into `storage/uploads` with
  randomized filenames; metadata is linked back to the owning user via the
  `attachments` table. Maximum size 25 MB; mime allow-list enforced.
- **Import/export**: CSV import and export of contacts via `fgetcsv` /
  `fputcsv`. Export files are saved to `storage/exports` and tracked in
  `import_export_jobs`.
- **API errors (MAIL-12)**: simulate-and-log dashboard captures UI-visible
  error states (e.g. `invalid_input`, `permission_denied`,
  `payload_too_large`) and writes them to `api_errors` for review.
- **Determinism**: the seed script bakes in fixed IDs / timestamps / inputs so
  re-runs produce identical state.
- **WebSocket**: no use case required WebSocket behaviour, so the
  Workerman transport is not enabled — ordinary HTTP is sufficient and used
  exclusively here.

---

## 9. Project layout

```
P13/
├── composer.json
├── composer.lock
├── .env.example
├── Dockerfile
├── docker-compose.yml
├── README.md
├── bin/
│   ├── init-db.php           # reset DB + run migrations + seed
│   ├── reset-db.php          # alias of init-db.php
│   ├── smoke-test.php        # internal smoke test (no HTTP server needed)
│   ├── http-smoke.php        # HTTP-level smoke test against running server
│   ├── serve.cmd             # Windows launcher
│   └── serve.sh              # Linux/macOS launcher
├── public/
│   ├── index.php             # Slim front controller
│   ├── css/style.css
│   └── js/app.js
├── src/
│   ├── Application.php       # Slim App + DI container + route table
│   ├── Auth/
│   │   ├── AuthMiddleware.php
│   │   └── SessionService.php
│   ├── Controllers/          # 12 controllers, one per use case
│   ├── Database/
│   │   ├── Database.php
│   │   ├── Migrations.php
│   │   └── Seed.php
│   ├── Helpers/
│   │   └── ResponseHelper.php
│   └── Repositories/         # 10 PDO-backed repositories
├── storage/
│   ├── app.db                # SQLite database (created on first init)
│   ├── uploads/              # attachment files
│   └── exports/              # generated CSV exports
└── views/                    # 13 PHP-rendered templates
```

---

## 10. Acceptance criteria mapping (high level)

| Criterion (sample) | How it is satisfied |
|--------------------|---------------------|
| MAIL-01-FA-01 valid sign-in reaches dashboard | `POST /login` redirects to `/dashboard` on success; smoke test verifies 302→/dashboard |
| MAIL-01-FA-02 invalid credentials rejected | `AccountAccessController::login` returns 401 with `Invalid credentials` and records `success=0` |
| MAIL-01-FA-03 sign-out removes access | `SessionService::logout` deletes the session row and clears cookie; `/mail/*` then redirects to `/login` |
| MAIL-02-FA-01 valid overview returns data | `MailboxOverviewController::show` and API return folder/message lists; smoke test verifies 200 |
| MAIL-02-FA-03 cross-user access rejected | `MailboxRepository::folderById` checks `user_id`; PATCH on a foreign folder returns `not_found_or_forbidden` |
| MAIL-03-FA-01 valid send returns confirmation | Smoke test confirms 201 with `id` and `status: sent` |
| MAIL-03-FA-02 invalid input rejected | `MessageComposeController::apiPost` returns 422 with field errors when recipient/subject/body invalid |
| MAIL-04-FA-01 reading returns data | `MessageReadingController::apiGet` returns the message + attachments; smoke test verifies 200 |
| MAIL-04-FA-03 unauthorized rejected | `MessageRepository::findById` is keyed by `user_id`; foreign ids return 404 |
| MAIL-05-FA-01 valid file op stores metadata | `AttachmentHandlingController::apiPost` persists the row + writes the file; smoke test confirms 201 |
| MAIL-05-FA-02 invalid file rejected | 413 / 415 returned for oversized / disallowed mime; orphan rows never created |
| MAIL-05-FA-03 cannot access another user's file | `download` checks `(int) $att['user_id'] === (int) $user['id']`; API PATCH returns `not_found_or_forbidden` |
| MAIL-06-FA-01 authorized action updates record | `ContactRepository::update` only fires when ownership matches; smoke test verifies 201 + 200 |
| MAIL-06-FA-03 non-privileged user rejected | AuthMiddleware restricts privileged paths; cross-user `find` returns null → controller returns 404 |
| MAIL-07-FA-01 valid rule workflow | `FiltersAndRulesController::apiPost` creates a row; smoke test confirms 201 |
| MAIL-08-FA-01 authorized privileged action | Domain admin/sadm can create/update domains and aliases; smoke test confirms 200 |
| MAIL-08-FA-03 non-privileged user rejected | mail_user → 403; smoke test verifies 403 for system-admin endpoint |
| MAIL-09-FA-01 valid quarantine workflow | `QuarantineController::apiPatch` releases or deletes; smoke test confirms 200 |
| MAIL-10-FA-01 known filters return matches | `AuditRepository::search` filters by action/role/target/since; smoke test confirms 200 |
| MAIL-10-FA-03 private records do not appear | Repository queries are scoped; no PII or secrets returned for unauthorized actors |
| MAIL-11-FA-01 valid file op stores metadata | CSV import writes contacts and a job row; CSV export downloads a file |
| MAIL-11-FA-03 cannot access another user's file | `findJob` enforces ownership |
| MAIL-12-FA-01 valid integration logs state | `FrontendApiIntegrationController::apiPost` records `api_errors` and an audit event |
| MAIL-12-FA-03 unauthorized rejected | AuthMiddleware blocks API for unauthenticated callers; foreign-state changes return 404/403 |

---

## 11. Known limitations

- The application is single-threaded (PHP built-in server); for production
  deploy behind PHP-FPM + Nginx/Apache or Workerman.
- File storage is local-disk only; no S3 or other object-store adapter.
- All email is intra-application; there is no external MTA integration.
- No real-time push (no Workerman required by the prompt); UI refreshes
  happen via ordinary form submits and `fetch()`.
- The seed script always recreates the database when invoked with `init-db`
  or `reset-db`; use the API endpoints to grow data.
