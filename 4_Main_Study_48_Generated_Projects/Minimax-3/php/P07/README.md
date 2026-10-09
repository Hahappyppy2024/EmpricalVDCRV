# P07 — Cloud File-Sharing System (PHP / Slim 4)

Synthetic benchmark application implementing the P07 specification with PHP 8.3, Slim 4, PSR-7, SQLite (via PDO) and server-side session cookies. Browser UI uses server-rendered HTML, vanilla CSS and a small JavaScript helper for API forms. No external services are required: deterministic local adapters are provided for mail, object storage, encryption tokens, and seeded data.

## Prerequisites

- PHP 8.3+ with extensions `pdo_sqlite`, `openssl`, `mbstring`
- Composer 2.x
- Optional: Docker / Docker Compose

## Setup

```bash
cp .env.example .env
composer install
php bin/reset-db.php
```

The reset command rebuilds the SQLite database at `var/db/cloud.sqlite`, creates deterministic folders under `var/storage`, and populates fixtures for all roles, quotas, teams, folders, files, versions, shares, audit events, exports, trash items and admin settings.

## Start the development server

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Browse to <http://127.0.0.1:8080>. Use the seeded accounts below.

## Seed accounts

| Email | Password | Role |
| --- | --- | --- |
| admin@example.com | `Admin#2026` | admin |
| user@example.com | `User#2026` | user |
| recipient@example.com | `User#2026` | recipient |
| other@example.com | `User#2026` | user |

## Database reset command

```bash
php bin/reset-db.php
```

## Docker

```bash
docker compose up --build
```

`docker-compose.yml` builds the image, runs `php bin/reset-db.php` to seed, and exposes the app on <http://127.0.0.1:8080>.

## Useful URLs

| Path | Description |
| --- | --- |
| `/` | Landing page with seed account summary |
| `/login`, `/register`, `/recover` | FILE-01 account access flows |
| `/dashboard` | Authenticated summary |
| `/upload` | FILE-02 file upload page |
| `/folders` | FILE-03 folder management |
| `/files`, `/files/{id}` | FILE-04 file download/preview |
| `/shares` | FILE-05 sharing links |
| `/teams` | FILE-06 team spaces |
| `/search` | FILE-07 search |
| `/files/{id}/versions` | FILE-08 version history |
| `/trash` | FILE-09 trash and restore |
| `/quota` | FILE-10 storage quota |
| `/activity` | FILE-11 audit log and exports |
| `/admin` | FILE-12 admin console |
| `/s/{token}` | Share page for FILE-05 |
| `/audit/{fileId}/download` | Download an audit export |

## API endpoints

All routes follow `/api/file/{use_case}` per the specification. `GET` returns a list/data view, `POST` creates or performs a primary action, `PATCH /api/file/{use_case}/{id}` updates or completes a workflow item. CSRF tokens are required for `POST`, `PUT`, `PATCH`, `DELETE` and are issued automatically in `<meta name="csrf-token">`.

| Use case | API path | Methods |
| --- | --- | --- |
| FILE-01 | `/api/file/account_access` | GET, POST, PATCH `/{id}` |
| FILE-02 | `/api/file/file_upload` | GET, POST, PATCH `/{id}` |
| FILE-03 | `/api/file/folder_management` | GET, POST, PATCH `/{id}` |
| FILE-04 | `/api/file/file_download_and_preview` | GET, POST, PATCH `/{id}` |
| FILE-05 | `/api/file/sharing_links` | GET, POST, PATCH `/{id}` |
| FILE-06 | `/api/file/team_spaces` | GET, POST, PATCH `/{id}` |
| FILE-07 | `/api/file/search` | GET, POST, PATCH `/{id}` |
| FILE-08 | `/api/file/version_history` | GET, POST, PATCH `/{id}` |
| FILE-09 | `/api/file/trash_and_restore` | GET, POST, PATCH `/{id}` |
| FILE-10 | `/api/file/storage_quota` | GET, POST, PATCH `/{id}` |
| FILE-11 | `/api/file/audit_log_and_exports` | GET, POST, PATCH `/{id}` |
| FILE-12 | `/api/file/admin_console` | GET, POST, PATCH `/{id}` |

### Sample login payload

```bash
curl -X POST http://127.0.0.1:8080/api/file/account_access \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{"action":"login","email":"user@example.com","password":"User#2026"}'
```

## Smoke check

```bash
php -S 127.0.0.1:8080 -t public public/router.php &
SERVER_PID=$!
sleep 1
curl -s http://127.0.0.1:8080/ | head -n 4
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/login
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/api/file/account_access
kill $SERVER_PID
```

Expected output starts with HTTP `200` for the landing page, login page and account access info endpoint.

## Deterministic features

- Password hashing uses `SHA-256(password . APP_SALT)` so the seeded fixtures are reproducible across resets.
- Share token verification iterates the table and matches via SHA-256 hashes.
- Mail is delivered to local files in `var/mail/`.
- Object storage copies uploaded files into `var/storage/` with random filenames and metadata.
- AES-256-GCM with `APP_KEY` encrypts share-token lookups.

## Use case traceability

| Use case | Main implementation files | Routes / pages |
| --- | --- | --- |
| FILE-01 | `src/Service/AccountService.php`, `src/Controller/WebController.php`, `src/Repository/AccountRepository.php`, `src/Repository/UserRepository.php` | `/api/file/account_access`, `/login`, `/register`, `/recover` |
| FILE-02 | `src/Service/FileService.php`, `src/Repository/FileRepository.php`, `src/Infrastructure/Storage.php` | `/api/file/file_upload`, `/upload` |
| FILE-03 | `src/Service/FolderService.php`, `src/Repository/FolderRepository.php`, `src/Repository/FolderLogRepository.php` | `/api/file/folder_management`, `/folders` |
| FILE-04 | `src/Service/DownloadService.php`, `src/Repository/FileRepository.php`, `src/Controller/WebController.php` | `/api/file/file_download_and_preview`, `/files`, `/files/{id}/download`, `/files/{id}/preview` |
| FILE-05 | `src/Service/ShareService.php`, `src/Repository/ShareRepository.php`, `src/Infrastructure/Token.php` | `/api/file/sharing_links`, `/shares`, `/s/{token}` |
| FILE-06 | `src/Service/TeamService.php`, `src/Repository/TeamRepository.php` | `/api/file/team_spaces`, `/teams` |
| FILE-07 | `src/Service/SearchService.php`, `src/Repository/SearchRepository.php`, `src/Repository/FileRepository.php` | `/api/file/search`, `/search` |
| FILE-08 | `src/Service/VersionService.php`, `src/Repository/FileRepository.php`, `src/Repository/VersionLogRepository.php` | `/api/file/version_history`, `/files/{id}/versions` |
| FILE-09 | `src/Service/TrashService.php`, `src/Repository/TrashRepository.php` | `/api/file/trash_and_restore`, `/trash` |
| FILE-10 | `src/Service/QuotaService.php`, `src/Repository/QuotaRepository.php` | `/api/file/storage_quota`, `/quota` |
| FILE-11 | `src/Service/AuditService.php`, `src/Repository/AuditRepository.php`, `src/Infrastructure/Audit.php` | `/api/file/audit_log_and_exports`, `/activity`, `/audit/{fileId}/download` |
| FILE-12 | `src/Service/AdminService.php`, `src/Repository/AdminRepository.php`, `src/Repository/UserRepository.php` | `/api/file/admin_console`, `/admin` |

## Layout

- `public/index.php`, `public/router.php` — Slim entrypoint and built-in server router.
- `src/Bootstrap/AppFactory.php` — DI wiring and route registration.
- `src/Infrastructure/*` — config, database, sessions, CSRF, storage, audit, mail, encryption, view.
- `src/Repository/*` — data access layer over PDO.
- `src/Service/*` — business rules per use case.
- `src/Controller/*` — Slim controllers (web pages + JSON dispatcher).
- `src/Middleware/*` — session, CSRF and API exception handling.
- `src/Templates/*.php` — server-rendered HTML views.
- `database/schema.php`, `database/seed.php` — schema and deterministic fixtures.
- `bin/reset-db.php` — database reset and seed command.
- `var/` — runtime data (sqlite, uploads, mail).
- `Dockerfile`, `docker-compose.yml` — containerized run.

## Configuration reference

See `.env.example` for documented variables:

```
APP_NAME, APP_ENV, APP_URL, APP_KEY, APP_SALT,
SESSION_LIFETIME, COOKIE_SECURE,
DB_PATH, STORAGE_PATH, MAIL_PATH,
DEFAULT_USER_QUOTA, DEFAULT_TEAM_QUOTA, RETENTION_DAYS
```

The provided values are tuned for offline execution. Changing `APP_KEY` invalidates previously issued encrypted share secrets.