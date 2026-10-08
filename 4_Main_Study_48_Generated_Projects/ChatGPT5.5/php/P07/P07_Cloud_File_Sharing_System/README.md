# Northstar Cloud — P07 Cloud File Sharing System

Complete offline PHP 8.3 / Slim 4 cloud file-sharing application implementing FILE-01 through FILE-12. Business data and server-side sessions use SQLite; uploaded bytes use a deterministic local filesystem adapter indexed by SQLite.

## Requirements

- PHP 8.3 with `pdo_sqlite`
- Composer 2
- Optional: Xdebug 3 with coverage mode for branch/path measurement
- Optional: Docker with Compose

## Local setup and run

```bash
composer install
php bin/reset-database.php
php -S 0.0.0.0:8080 -t public public/router.php
```

Open <http://localhost:8080/app.html>. The commands use the documented local defaults; `.env.example` lists environment variables that may be exported when overriding them. Run commands from the project root so relative database and upload paths resolve consistently.

## Seed accounts

All seed accounts use password `Password123!`.

| Actor | Email | Role/status |
|---|---|---|
| Alice | `alice@example.test` | user; Research Team administrator |
| Bob | `bob@example.test` | user; Research Team member |
| Disabled fixture | `disabled@example.test` | disabled user |
| Avery | `admin@example.test` | system administrator |

Seed personal root folder IDs are Alice `1`, Bob `2`, disabled user `3`, and administrator `4`. Research Team has ID `1` and root folder ID `5`. Alice's Documents folder is `6`; the team Project Alpha folder is `7`.

## Functional tests

Each business use case has exactly one primary functional test file. Tests drive the complete Slim HTTP layer with isolated temporary SQLite databases and upload directories.

```bash
php bin/run-functional-tests.php
```

Expected result: `12 use-case files, 183 assertions, 0 failures.`

## Coverage

```bash
XDEBUG_MODE=coverage php bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

Verified environment: PHP 8.3.6 and Xdebug 3.2.0. The committed measurement is:

- Line: 693/729 = 95.06%
- Branch: 363/404 = 89.85%
- Path: 143/4496 = 3.18%

Coverage is restricted to `src/*.php`. Branch coverage is the acceptance metric; path coverage is reported diagnostically because complete paths grow combinatorially.

## Docker

```bash
docker compose up --build
```

The application is then available on <http://localhost:8080>. The named `cloud-data` volume retains the SQLite database and uploaded files.

## Use-case traceability

| ID | Workflow | Main routes | Functional test |
|---|---|---|---|
| FILE-01 | Account access | `/api/auth/register`, `/login`, `/logout`, `/password-reset-*` | `tests/Functional/FILE01AccountAccessTest.php` |
| FILE-02 | File upload | `POST /api/folders/{folderId}/files` | `tests/Functional/FILE02FileUploadTest.php` |
| FILE-03 | Folder management | `/api/folders`, `/children`, `/move` | `tests/Functional/FILE03FolderManagementTest.php` |
| FILE-04 | Download and preview | `/api/files/{fileId}/content`, `/preview`, version content | `tests/Functional/FILE04DownloadPreviewTest.php` |
| FILE-05 | Sharing links | file/folder `/shares`, `DELETE /api/shares/{id}`, public token read | `tests/Functional/FILE05SharingLinksTest.php` |
| FILE-06 | Team spaces | `/api/team-spaces` and member management | `tests/Functional/FILE06TeamSpacesTest.php` |
| FILE-07 | Search | `GET /api/search/files` | `tests/Functional/FILE07SearchTest.php` |
| FILE-08 | Version history | file `/versions` and `/restore` | `tests/Functional/FILE08VersionHistoryTest.php` |
| FILE-09 | Trash and restore | `/api/trash`, item `/trash`, restore and permanent delete | `tests/Functional/FILE09TrashRestoreTest.php` |
| FILE-10 | Storage quota | `/api/storage/quota`, admin user quota | `tests/Functional/FILE10StorageQuotaTest.php` |
| FILE-11 | Audit log/export | team `/audit-events` and `.csv` | `tests/Functional/FILE11AuditLogExportsTest.php` |
| FILE-12 | Admin console | `/api/admin/users`, user update, retention settings | `tests/Functional/FILE12AdminConsoleTest.php` |

## Main implementation modules

- `AuthAdminRoutes.php`: account, quota, user administration, retention
- `FileFolderRoutes.php`: folders, uploads, content, shares, search, versions
- `TeamTrashRoutes.php`: teams, membership, trash, restore, audit exports
- `CloudRepository.php`: authorization, quota calculation, local blob storage, audit persistence
- `Seeder.php`: deterministic actors, roots, team membership, files, share, audit data

All API failures use `{ "error": { "code", "message", "fields" } }`. Production responses do not include stack traces, filesystem paths, database errors, password hashes, or session identifiers.
