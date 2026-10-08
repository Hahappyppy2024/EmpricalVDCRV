# P07 — Cloud File-Sharing System

A complete, runnable cloud file-sharing web application (synthetic benchmark project) built with
**PHP 8.3 + Slim 4 + SQLite + vanilla JavaScript + Workerman (WebSocket)**.

It implements all 12 use cases of the P07 specification: account access, file upload, folder
management, download/preview, sharing links, team spaces, search, version history, trash/restore,
storage quota, audit log & exports, and the admin console.

> This is a synthetic benchmark system aligned with the high-level workflow categories of
> Nextcloud Server (files, folders, shares, tags, versions, trash, activity logs, quota, team
> spaces, and admin storage policies). It is not a clone of the real-world project.

---

## Table of contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Environment configuration](#environment-configuration)
4. [Database reset and seed](#database-reset-and-seed)
5. [Start the application](#start-the-application)
6. [Seed accounts](#seed-accounts)
7. [Usage overview](#usage-overview)
8. [Docker](#docker)
9. [Project layout](#project-layout)
10. [API contract](#api-contract)
11. [Use-case traceability](#use-case-traceability)
12. [Known notes](#known-notes)

---

## Prerequisites

- PHP **8.3** (with `pdo_sqlite` enabled; the `pdo_sqlite` and `sqlite3` extensions are required)
- [Composer](https://getcomposer.org/) 2.x
- (Optional) [Docker](https://www.docker.com/) + Docker Compose for containerized execution

Verify:

```bash
php -v          # PHP 8.3.x
composer --version
php -m | grep -i -E "pdo_sqlite|sqlite3"   # must list both
```

## Installation

```bash
composer install
```

This creates `vendor/` and the committed `composer.lock` is used to install the exact pinned
dependencies.

## Environment configuration

Copy the example environment file and adjust if needed:

```bash
cp .env.example .env
```

All values already work out of the box for local execution. Key settings:

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_HOST` / `APP_PORT` | `127.0.0.1` / `8080` | Web server bind address / port |
| `DB_PATH` | `storage/db/cloudfs.sqlite` | SQLite database file |
| `STORAGE_PATH` | `storage/files` | Uploaded file blob directory |
| `SESSION_COOKIE_NAME` | `cfss_session` | HTTP-only session cookie name |
| `SESSION_LIFETIME_SECONDS` | `7200` | Session lifetime |
| `WS_HOST` / `WS_PORT` | `127.0.0.1` / `8282` | Workerman WebSocket server |

## Database reset and seed

```bash
php bin/reset-db.php
# or
composer db:reset
```

This deletes the current database, recreates the full schema (`src/Database/schema.sql`) and
inserts deterministic seed fixtures:

- 3 users (`admin`, `alice`, `bob`)
- storage policies + blocked file types (`exe`, `bat`, `php`)
- folders, files with tags/descriptions, file versions
- public and private (password-protected) share links
- a team space (`Marketing`) with members
- trash items, quota records, and audit history

## Start the application

```bash
composer start
# equivalent to:
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080 in a browser.

### Real-time WebSocket server (optional)

The app works fully over HTTP without it. For live activity notifications:

```bash
php bin/websocket.php start
# or
composer realtime
```

The WebSocket server listens on `ws://127.0.0.1:8282`, shares the same SQLite database, and
broadcasts events inserted into the `realtime_events` table (file uploads, versions, admin policy
changes, etc.). The browser client connects automatically on every page and shows a toast for each
live event.

Smoke check after starting both servers:

```bash
curl http://127.0.0.1:8080/api/health
# {"ok":true,"app":"cloud-file-sharing-system","database":"sqlite","status":"healthy"}
```

## Seed accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | Admin |
| `alice` | `password123` | User |
| `bob` | `password123` | User |

## Usage overview

| Page | URL | Description |
| --- | --- | --- |
| Dashboard / files | `/dashboard` | Browse files and folders, recent activity |
| Upload | `/upload` | Upload files with folder, description, tags |
| Folders | `/folders` | Create / rename / delete folders |
| Sharing | `/sharing` | Create, revoke, and list share links |
| Teams | `/teams` | Team spaces with role-based membership |
| Search | `/search` | Search by name, tag, owner, date |
| Versions | `/versions` | Upload new versions, restore old ones |
| Trash | `/trash` | Restore or permanently purge deleted files |
| Quota | `/quota` | Storage usage; admins see all users |
| Audit | `/audit` | Activity log and CSV/JSON exports |
| Account | `/account-access` | Sign-in/sign-out/reset history |
| Admin | `/admin` | Users, storage policies, blocked types (admin only) |
| Share link | `/s/{token}` | Anonymous view/download of a shared file |

## Docker

```bash
docker compose up --build
```

- Web app: http://localhost:8080
- WebSocket: ws://localhost:8282
- The container resets and seeds the database on every start (`php bin/reset-db.php`), so the state
  is always deterministic. Persistent storage lives in the named volume `cloudfs_data`.

To stop:

```bash
docker compose down
```

## Project layout

```
P07/
├── public/
│   ├── index.php          # front controller
│   ├── css/style.css      # styles
│   └── js/app.js          # vanilla JS helpers + WebSocket client
├── src/
│   ├── App.php            # Slim app bootstrap + all routes
│   ├── Config.php         # environment configuration loader
│   ├── Controllers/       # one controller per use case
│   ├── Repositories/      # data-access layer
│   ├── Services/          # auth, session, validation, storage, quota, audit, realtime
│   ├── Middleware/        # session, auth, admin middleware
│   ├── Database/          # Database, schema.sql, Seeder
│   └── Views/             # server-served HTML templates (PhpRenderer)
├── bin/
│   ├── reset-db.php       # reset + seed
│   └── websocket.php      # Workerman WebSocket server
├── storage/               # SQLite db + uploaded blobs (gitignored)
├── composer.json          # exact pinned direct dependencies
├── composer.lock          # committed lockfile
├── .env.example
├── Dockerfile
└── docker-compose.yml
```

## API contract

The specification requires these paths to exist with GET/POST/PATCH semantics. All endpoints
require a valid session cookie (except the share-recipient routes and `/api/health`). Responses
are JSON. PATCH bodies are JSON; the FILE-02/FILE-08 POST endpoints accept `multipart/form-data`.

| Use case | Route |
| --- | --- |
| FILE-01 | `GET/POST /api/file/account_access`, `PATCH /api/file/account_access/{id}` |
| FILE-02 | `GET/POST /api/file/file_upload`, `PATCH /api/file/file_upload/{id}` |
| FILE-03 | `GET/POST /api/file/folder_management`, `PATCH /api/file/folder_management/{id}` |
| FILE-04 | `GET/POST /api/file/file_download_and_preview`, `PATCH /api/file/file_download_and_preview/{id}`, `GET /files/{id}/download`, `GET /files/{id}/preview`, `GET /s/{token}`, `GET /s/{token}/download` |
| FILE-05 | `GET/POST /api/file/sharing_links`, `PATCH /api/file/sharing_links/{id}` |
| FILE-06 | `GET/POST /api/file/team_spaces`, `PATCH /api/file/team_spaces/{id}` |
| FILE-07 | `GET/POST /api/file/search`, `PATCH /api/file/search/{id}` |
| FILE-08 | `GET/POST /api/file/version_history`, `PATCH /api/file/version_history/{id}` |
| FILE-09 | `GET/POST /api/file/trash_and_restore`, `PATCH /api/file/trash_and_restore/{id}` |
| FILE-10 | `GET/POST /api/file/storage_quota`, `PATCH /api/file/storage_quota/{id}` |
| FILE-11 | `GET/POST /api/file/audit_log_and_exports`, `PATCH /api/file/audit_log_and_exports/{id}`, `GET /api/file/audit_log_and_exports/export/{format}` |
| FILE-12 | `GET/POST /api/file/admin_console`, `PATCH /api/file/admin_console/{id}` |

Example — create a share link:

```bash
curl -X POST http://127.0.0.1:8080/api/file/sharing_links \
  -H "Content-Type: application/json" \
  -b "cfss_session=<token>" \
  -d '{"file_id":1,"scope":"public","permissions":"download","expires_at":""}'
```

## Use-case traceability

| Use case | Title | Acceptance criteria | Main files / routes |
| --- | --- | --- | --- |
| FILE-01 | Account access | FILE-01-FA-01..03 | `src/Controllers/AuthController.php`, `src/Controllers/AccountAccessController.php`, `src/Services/AuthService.php`, `src/Services/SessionService.php`, routes `/register`, `/login`, `/logout`, `/reset`, `/api/file/account_access` |
| FILE-02 | File upload | FILE-02-FA-01..03 | `src/Controllers/FileUploadController.php`, `src/Repositories/FileRepository.php`, `src/Services/StorageService.php`, `src/Services/QuotaService.php`, routes `/upload`, `/api/file/file_upload` |
| FILE-03 | Folder management | FILE-03-FA-01..03 | `src/Controllers/FolderManagementController.php`, `src/Repositories/FileRepository.php`, routes `/folders`, `/api/file/folder_management` |
| FILE-04 | File download and preview | FILE-04-FA-01..03 | `src/Controllers/DownloadPreviewController.php`, routes `/files/{id}/download`, `/files/{id}/preview`, `/s/{token}`, `/api/file/file_download_and_preview` |
| FILE-05 | Sharing links | FILE-05-FA-01..03 | `src/Controllers/SharingLinksController.php`, `src/Repositories/FileRepository.php`, routes `/sharing`, `/api/file/sharing_links` |
| FILE-06 | Team spaces | FILE-06-FA-01..03 | `src/Controllers/TeamSpacesController.php`, `src/Repositories/TeamRepository.php`, routes `/teams`, `/api/file/team_spaces` |
| FILE-07 | Search | FILE-07-FA-01..03 | `src/Controllers/SearchController.php`, routes `/search`, `/api/file/search` |
| FILE-08 | Version history | FILE-08-FA-01..03 | `src/Controllers/VersionHistoryController.php`, `src/Repositories/FileRepository.php`, routes `/versions`, `/api/file/version_history` |
| FILE-09 | Trash and restore | FILE-09-FA-01..03 | `src/Controllers/TrashController.php`, `src/Repositories/FileRepository.php`, routes `/trash`, `/api/file/trash_and_restore` |
| FILE-10 | Storage quota | FILE-10-FA-01..03 | `src/Controllers/QuotaController.php`, `src/Services/QuotaService.php`, routes `/quota`, `/api/file/storage_quota` |
| FILE-11 | Audit log and exports | FILE-11-FA-01..03 | `src/Controllers/AuditController.php`, `src/Services/AuditService.php`, routes `/audit`, `/api/file/audit_log_and_exports` |
| FILE-12 | Admin console | FILE-12-FA-01..03 | `src/Controllers/AdminConsoleController.php`, `src/Repositories/SettingsRepository.php`, `src/Repositories/UserRepository.php`, routes `/admin`, `/api/file/admin_console` |

## Known notes

- Session records are persisted in SQLite (`sessions` table); the session is identified by an
  HTTP-only cookie (`cfss_session`).
- All external behavior is deterministic and offline: there is no email provider, payment gateway,
  external object store, or third-party API. Password resets are recorded as account-access audit
  events (no email is sent).
- The PHP built-in web server is single-threaded; the Workerman WebSocket server runs as a separate
  process sharing the same SQLite file. Use `composer realtime` for the WebSocket process.
- Quota is enforced at upload time; `storage_quota.used_bytes` is recomputed on upload and can be
  recalculated from the Quota page.
