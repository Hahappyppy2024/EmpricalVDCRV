# P16 — Server Monitoring & Job Control Panel

A complete, self-contained web application for server monitoring and job control, generated as a
synthetic benchmark project aligned with **Cacti 1.2.26**. It implements the twelve use cases
`SYS-01` … `SYS-12` as one integrated application on a single shared database.

## Technology stack

| Layer | Choice |
| --- | --- |
| Language / runtime | PHP 8.3 |
| Web framework | Slim 4 (PSR-7) + FastRoute |
| Database | SQLite via PDO (repository/data-access layer) |
| Authentication | Server-side sessions in SQLite, HTTP-only cookie |
| Browser client | Server-served HTML, CSS, vanilla JavaScript |
| Real-time | Optional Workerman WebSocket push sharing the same SQLite DB |
| Dependency tooling | Composer, exact pinned versions + committed `composer.lock` |

## Project layout

```
P16/
├── public/                 # front controller + assets (css, js)
├── src/
│   ├── Modules/            # 12 per-use-case service classes (business logic + ownership rules)
│   ├── Controller/         # AuthController, PageController, ApiController
│   ├── Middleware/         # AuthMiddleware, AdminMiddleware
│   ├── AppBootstrap.php    # Slim app, services, all routes
│   ├── Database.php        # PDO data-access helpers
│   ├── SessionService.php  # persistent sessions
│   ├── AuthService.php     # register / login / logout + account-access history
│   ├── AuditService.php    # audit event writer
│   ├── Validator.php, View.php, Config.php, ErrorHandler.php
├── templates/              # server-rendered pages (layout + 12 module pages + auth)
├── database/schema.sql     # SQLite schema (all tables + constraints)
├── database/seed.php       # deterministic seed fixture
├── bin/reset-db.php        # reset + reseed command
├── bin/websocket.php       # Workerman WebSocket push server
├── storage/                # SQLite DB, backups, uploads, mirrored logs
├── .env.example / .env
├── composer.json / composer.lock
├── Dockerfile / docker-compose.yml
└── README.md
```

## Prerequisites

- PHP 8.1+ (tested on 8.3) with extensions `pdo_sqlite`, `mbstring`, `json`, `curl`
- Composer 2.x
- Optional: Docker + Docker Compose, or Workerman (bundled via Composer)

## Setup

```bash
# 1. Install locked dependencies
composer install

# 2. Create environment file
cp .env.example .env          # all local defaults are safe to keep

# 3. Initialize / seed the database (creates storage/monitoring.sqlite)
php bin/reset-db.php
```

The seed fixture creates all roles, users, dashboard metrics, log files, services, jobs, run
history, backups, config keys, alerts, health targets, API tokens and audit events deterministically.

## Start the application

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open <http://127.0.0.1:8080>. The built-in dev server re-reads the code on every request, so no
restart is needed during development.

### Optional real-time push (WebSocket)

```bash
php bin/websocket.php start      # Workerman daemon on ws://127.0.0.1:9090
```

Then set `WS_ENABLED=1` in `.env` and reload the dashboard. The dashboard receives live metric
snapshots over the socket and falls back to 15-second REST polling when the socket is absent.

## Seed accounts

| Username | Password | Role | Access |
| --- | --- | --- | --- |
| `admin` | `admin123` | admin | everything, incl. Configuration Editor, API Tokens, Audit & admin operations |
| `operator` | `operator123` | operator | monitoring workflows + service control + jobs |
| `viewer` | `viewer123` | viewer | read-only (cannot control services, jobs, config, tokens, audit) |

New registrations always receive the `operator` role.

## Docker

```bash
docker compose up --build
# app:   http://127.0.0.1:8080
# websocket: ws://127.0.0.1:9090 (set WS_ENABLED=1 to use)
```

The container runs `php bin/reset-db.php` on boot (resets + seeds), serves the app with the PHP
built-in server, and exposes an HTTP health check at `/api/monitor/health`. Building requires
network access to pull the `php:8.3-cli` and `composer:2` base images.

## HTTP API

Every module is exposed under `/api/sys/{module}` with `GET` (list, query filters accepted),
`POST` (create), `PATCH /{id}` (update/actions), `DELETE /{id}`. JSON requests require
`Content-Type: application/json`. Errors are deterministic JSON: `{ok:false, error, code}` with
422/403/404/500 semantics. Sessions authenticate via the HTTP-only `p16_session` cookie.

Module-specific endpoints:

| Endpoint | Purpose |
| --- | --- |
| `GET /api/sys/server_dashboard/latest` | latest metric snapshot per server |
| `GET /api/sys/log_viewer/files` | list log files with entry counts |
| `GET /api/sys/log_viewer/preview?file=&q=` | filtered log entries |
| `GET /api/sys/log_viewer/download?file=` | download a log file |
| `POST /api/sys/backup_manager/upload` | upload a backup file (multipart `file`) |
| `GET /api/sys/backup_manager/{id}/download` | download a backup |
| `POST /api/sys/backup_manager/{id}/restore` | mark a backup restored |
| `POST /api/sys/health_check_targets/{id}/check` | run a health check now |
| `POST /api/sys/api_token_manager` | create a token (plain token returned once) |
| `GET /api/sys/audit_logs_and_admin_operations/users` | active users for token ownership |
| `GET /api/sys/audit_logs_and_admin_operations/operators` | operators for admin management |

Public endpoints:

| Endpoint | Purpose |
| --- | --- |
| `GET /api/monitor/health` | container/liveness health check |
| `GET /api/monitor/metrics?token=…` or `Authorization: Bearer …` | token-gated current metrics (demonstrates API tokens) |

## Deterministic local adapters

Everything runs offline after `composer install`:

- **Monitoring targets**: HTTP targets on `127.0.0.1`/`localhost` ports `8787`→`200/up` and
  `8788`→`503/down` are simulated; other HTTP targets use a real request (2 s timeout) and TCP
  targets use a real connect (1 s timeout).
- **Background jobs**: running a job deterministically computes exit status / retries / duration
  from the profile + job id and stores a `job_runs` record.
- **Backups**: creating a backup writes a deterministic manifest (config + latest metrics + jobs)
  to `storage/backups` and records SHA-256 / size metadata.
- **Email / payments / object storage**: not required by any use case in this project.

## Use-case traceability

| Use case | Primary actors | Main files | API routes | Tables |
| --- | --- | --- | --- | --- |
| SYS-01 Account access | operator; admin | `Modules/AccountAccessService.php`, `Controller/AuthController.php`, `templates/pages/account.php`, `templates/auth/*` | `GET/POST /api/sys/account_access`, `PATCH /{id}` | `users`, `sessions`, `account_access` |
| SYS-02 Server dashboard | operator | `Modules/ServerDashboardService.php`, `templates/pages/dashboard.php` | `GET/POST /api/sys/server_dashboard`, `PATCH /{id}`, `GET /latest` | `server_dashboard` |
| SYS-03 Log viewer | operator | `Modules/LogViewerService.php`, `templates/pages/logs.php` | `GET/POST /api/sys/log_viewer`, `PATCH /{id}`, `files`, `preview`, `download` | `log_entries` (+ mirrored `storage/logs/*.log`) |
| SYS-04 Service control | operator; admin | `Modules/ServiceControlService.php`, `templates/pages/services.php` | `GET/POST /api/sys/service_control`, `PATCH /{id}` (start/stop/restart/inspect) | `mock_services` |
| SYS-05 Job scheduler | operator; admin | `Modules/JobSchedulerService.php`, `templates/pages/jobs.php` | `GET/POST /api/sys/job_scheduler`, `PATCH /{id}` (run/pause/resume), `DELETE` | `jobs` |
| SYS-06 Job execution history | operator | `Modules/JobExecutionHistoryService.php`, `templates/pages/job_history.php` | `GET/POST /api/sys/job_execution_history`, `PATCH /{id}` | `job_runs` |
| SYS-07 Backup manager | operator; admin | `Modules/BackupManagerService.php`, `templates/pages/backups.php` | `GET/POST /api/sys/backup_manager`, `PATCH /{id}`, `upload`, `download`, `restore`, `DELETE` | `backups`, `stored_files` |
| SYS-08 Configuration editor | admin | `Modules/ConfigurationEditorService.php`, `templates/pages/config.php` | `GET/POST /api/sys/configuration_editor`, `PATCH /{id}` (propose/approve/reject) | `config_keys` |
| SYS-09 Alert center | operator | `Modules/AlertCenterService.php`, `templates/pages/alerts.php` | `GET/POST /api/sys/alert_center`, `PATCH /{id}` (acknowledge/assign/close/comment) | `alerts` |
| SYS-10 Health check targets | operator; admin | `Modules/HealthCheckTargetsService.php`, `templates/pages/health.php` | `GET/POST /api/sys/health_check_targets`, `PATCH /{id}`, `POST /{id}/check` | `health_targets` |
| SYS-11 API token manager | admin | `Modules/ApiTokenManagerService.php`, `templates/pages/tokens.php` | `GET /api/sys/api_token_manager`, `POST` (create), `PATCH /{id}` (revoke), `DELETE`; `GET /api/monitor/metrics` | `api_tokens` |
| SYS-12 Audit logs & admin operations | admin | `Modules/AuditLogsAndAdminOperationsService.php`, `templates/pages/audit.php` | `GET/POST /api/sys/audit_logs_and_admin_operations`, `PATCH /{id}`, `users`, `operators` | `audit_events`, `operator_actions` |

## Security & business-rule notes

- Sessions are stored in SQLite (`sessions` table); the cookie is HTTP-only with `SameSite=Lax`.
- Ownership boundaries are enforced per record: operators/viewers only see and mutate their own
  jobs, runs, backups, snapshots and access records; admins see everything.
- Privileged operations (service actions, config changes, token create/revoke, operator management)
  are always written to the audit log (`audit_events`).
- Invalid state transitions are rejected with 422 without partial persistence; unknown or
  out-of-scope records return 404; error bodies never expose stack traces.
- New registrations and API-token owners are linked deterministically to their records.

## Smoke check

`storage/smoke.ps1` (Windows PowerShell, optional, not a test suite) starts the app on port 8081,
exercises all twelve modules over real HTTP, and reports a pass/fail summary. A clean run reports
`68 passed, 0 failed`.
