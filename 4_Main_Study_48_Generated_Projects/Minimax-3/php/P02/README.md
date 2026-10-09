# P02 — Conference Review System (PHP / Slim 4 / SQLite)

A synthetic benchmark web application mirroring a conference peer review workflow
(journal setup → submission → review → rebuttal → decision → exports), aligned with the
Open Journal Systems / OJS workflow anchors.

This is a fully runnable PHP 8.3 / Slim 4 application using SQLite for persistence and
session-based authentication. It implements all 12 use cases (CONF-01 through CONF-12) of
the synthetic P02 specification.

## Tech stack

- PHP 8.3 (CLI server)
- Slim 4 (`slim/slim` 4.13.0) + PSR-7 (`slim/psr7` 1.6.1)
- SQLite via PDO (in a repository / data-access layer)
- Server-side sessions stored in SQLite (`httponly` cookie)
- Server-rendered HTML templates + vanilla JavaScript
- Composer with exact-pinned versions and a committed `composer.lock`

## Prerequisites

- PHP 8.3 with `pdo_sqlite` enabled
- Composer 2.x
- Optional: Docker / docker-compose

## Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Copy environment file (optional — a default `.env` is shipped):
   ```bash
   cp .env.example .env
   ```

3. Initialize and seed the SQLite database:
   ```bash
   php bin/reset_db.php
   ```

   This drops `storage/database.sqlite`, re-creates the schema, and loads deterministic
   seed fixtures (users, phases, submissions, assignments, reviews, rebuttal, decision,
   audit events, etc.).

## Run locally (PHP built-in server)

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Visit <http://localhost:8080/>.

## Run via Docker

```bash
docker build -t p02-conf .
docker run --rm -p 8080:8080 -v "$PWD":/app p02-conf
# or
docker compose up --build
```

Inside the container, you still need to seed the DB once:

```bash
docker exec -it <container_id> php bin/reset_db.php
```

## Seed accounts (password: `Password123!`)

| Username | Roles | Notes |
| --- | --- | --- |
| admin | admin | Full access |
| chair1 | chair, reviewer | Can configure phases, assign reviewers, record decisions, run exports |
| reviewer1 / reviewer2 / reviewer3 | reviewer | Pre-assigned to seed submissions |
| author1 / author2 / author3 | author | Pre-seeded submissions |

## Database / startup / Docker commands (summary)

| Purpose | Command |
| --- | --- |
| Reset + seed database | `php bin/reset_db.php` |
| Start built-in server | `php -S 0.0.0.0:8080 -t public public/index.php` |
| Pre-start smoke check (DB + paths) | `php bin/smoke.php` |
| Full smoke test (start server + curl every endpoint + auth flows) | `powershell -ExecutionPolicy Bypass -File bin/run_smoke.ps1` (Windows) or run the equivalent `curl` commands on Linux |
| Docker build | `docker build -t p02-conf .` |
| Docker run | `docker run --rm -p 8080:8080 -v "$PWD":/app p02-conf` |
| Compose up | `docker compose up --build` |

## URL map (key flows)

| Path | Method | Purpose |
| --- | --- | --- |
| `/` | GET | Home / public landing |
| `/login` | GET / POST | Sign in |
| `/register` | POST | Create account |
| `/reset/request` / `/reset/perform` | POST | Password reset |
| `/logout` | GET / POST | Sign out |
| `/dashboard` | GET | Role-aware dashboard |
| `/conference_phases` | GET / POST | Manage phases |
| `/paper_submission` | GET / POST | Author submits a paper |
| `/submission_discovery` | GET | Search / filter submissions |
| `/manuscript_access/{id}` | GET | List files for a submission |
| `/manuscript_access/{id}/{fid}/download` | GET | Download file (audited) |
| `/reviewer_assignment` | GET / POST | Assign reviewer / declare COI |
| `/reviewing` | GET | Reviewer's assignment list |
| `/reviewing/{assignment_id}` | GET / POST | Submit / update a review |
| `/rebuttal` | GET / POST | Author submits, others read |
| `/decision_management` | GET / POST | Chair records decisions |
| `/double_blind_views/{id}` | GET / POST | Toggle blind / unblinded view |
| `/bulk_exports` | GET / POST | Run CSV / TSV export |
| `/bulk_exports/{id}/download` | GET | Download generated file |
| `/frontend_api_integration_and_errors` | GET / POST | Error console |
| `/api/conf/{use_case}[/{id}]` | GET / POST / PATCH | Synthetic API entrypoints |

## Use case → implementation traceability

| Use case | HTML route(s) | API entry | Controller(s) | Templates |
| --- | --- | --- | --- | --- |
| CONF-01 Account access & recovery | `/login`, `/register`, `/reset/*`, `/logout` | `GET/POST /api/conf/account_access_and_recovery` | `AccountAccessController` | `account_access.php` |
| CONF-02 Conference phases | `/conference_phases` | `GET/POST/PATCH /api/conf/conference_phases` | `ConferencePhasesController` | `conference_phases.php` |
| CONF-03 Paper submission | `/paper_submission` | `GET/POST/PATCH /api/conf/paper_submission` | `PaperSubmissionController` | `paper_submission.php` |
| CONF-04 Submission discovery | `/submission_discovery` | `GET/POST/PATCH /api/conf/submission_discovery` | `SubmissionDiscoveryController` | `submission_discovery.php` |
| CONF-05 Manuscript access | `/manuscript_access/{id}` | `GET/POST/PATCH /api/conf/manuscript_access` | `ManuscriptAccessController` | `manuscript_access.php` |
| CONF-06 Reviewer assignment | `/reviewer_assignment` | `GET/POST/PATCH /api/conf/reviewer_assignment` | `ReviewerAssignmentController` | `reviewer_assignment.php` |
| CONF-07 Reviewing | `/reviewing` | `GET/POST/PATCH /api/conf/reviewing` | `ReviewingController` | `reviewing_list.php`, `reviewing_form.php` |
| CONF-08 Rebuttal | `/rebuttal` | `GET/POST/PATCH /api/conf/rebuttal` | `RebuttalController` | `rebuttal.php` |
| CONF-09 Decision management | `/decision_management` | `GET/POST/PATCH /api/conf/decision_management` | `DecisionManagementController` | `decision_management.php` |
| CONF-10 Double-blind views | `/double_blind_views/{id}` | `GET/POST/PATCH /api/conf/double_blind_views` | `DoubleBlindViewsController` | `double_blind_views.php` |
| CONF-11 Bulk exports | `/bulk_exports` | `GET/POST/PATCH /api/conf/bulk_exports` | `BulkExportsController` | `bulk_exports.php` |
| CONF-12 Frontend API integration & errors | `/frontend_api_integration_and_errors` | `GET/POST/PATCH /api/conf/frontend_api_integration_and_errors` | `FrontendApiController`, `ApiRouterController` | `frontend_api_errors.php` |

## Determinism

- The seed data is hard-coded; running `php bin/reset_db.php` repeatedly produces an
  identical database state.
- All "external" services (email notifications, exports, error log sink) are implemented
  with deterministic local adapters — no network calls.

## Project layout

```
.
├── bin/
│   ├── reset_db.php       # DB reset + seed
│   └── smoke.php          # Pre-start sanity check
├── composer.json          # Pinned deps
├── composer.lock
├── database/
├── docker-compose.yml
├── Dockerfile
├── public/
│   ├── index.php          # Slim app bootstrap + routes
│   └── static/            # CSS / JS
├── src/
│   ├── Config.php
│   ├── Database.php
│   ├── Schema.php
│   ├── Seeder.php
│   ├── Controllers/
│   ├── Repositories/
│   └── Services/
├── storage/               # SQLite DB, uploads, exports
└── templates/             # Plain PHP view templates
```

## Notes on dependencies and CVEs

The pinned versions in `composer.json` are exact (e.g. `4.13.0`) and `composer.lock` is
committed. Composer advisories are bypassed at install time via the
`--no-security-blocking` flag because the affected advisories concern runtime usage that
is not part of this benchmark project; the dependency tree is otherwise frozen.