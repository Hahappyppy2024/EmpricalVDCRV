# P16 — Server Monitoring & Job Control Panel

A self-contained PHP 8.3 web application built with **Slim 4**, **Slim PSR-7**, **Slim PHP-View**, and a **SQLite** data layer. The application implements the full workflow contract of use cases **SYS-01** through **SYS-12** from the synthetic benchmark specification (real-world alignment target: Cacti 1.2.26).

The entire stack runs offline after `composer install`; no external services, payment gateways, or message brokers are required.

---

## Tech profile

| Layer | Choice |
| --- | --- |
| Language / runtime | PHP 8.3 |
| Framework | Slim 4.15 |
| Request / response | slim/psr7 1.7 |
| Templating | slim/php-view 3.4 |
| Container | Composer autoload + manual wiring |
| Persistence | SQLite (PDO, file-backed `data/app.sqlite`) |
| Authentication | HTTP-only session cookie (`p16_session`) backed by `sessions` table |
| Frontend | Server-rendered HTML, vanilla JS, single CSS file |
| Real-time adapter | HTTP polling (Workerman was not required for any use case) |
| Dependency tooling | Composer with committed `composer.lock` |
| Containerization | Dockerfile + docker-compose.yml |

---

## Project layout

```
P16/
├── composer.json / composer.lock
├── .env.example
├── Dockerfile / docker-compose.yml / docker-entrypoint.sh
├── README.md
├── bin/
│   ├── seed.php           # reset / apply fixtures
│   └── smoke.ps1          # PowerShell smoke-check script
├── public/
│   ├── index.php          # Slim entry point
│   └── assets/
│       ├── style.css
│       └── app.js
├── src/
│   ├── App.php            # bootstrap, route registration
│   ├── Database/          # Connection + Migrator + schema.php
│   ├── Repositories/      # PDO-backed repositories for every entity
│   ├── Services/          # SessionService, AuditService, Validator
│   ├── Seed/Seeder.php    # deterministic fixtures
│   └── Controllers/       # 12 controllers — one per use case
└── templates/             # PHP-View templates (layout, login, dashboard, ...)
```

---

## Prerequisites

* PHP **8.3** with `pdo_sqlite`, `mbstring`, and the built-in development server.
* Composer 2.x.
* No additional OS-level libraries are needed beyond SQLite (bundled with PHP).

---

## Setup, database, startup

### 1. Install dependencies

```bash
composer install
```

The committed `composer.lock` is the authoritative version manifest.

### 2. Configure environment

```bash
cp .env.example .env
```

The `.env` file is read via `vlucas/phpdotenv`. Adjust paths, ports, or seed credentials as desired.

### 3. Reset / seed the database

```bash
# First-time setup OR rebuilding the SQLite database from scratch:
php bin/seed.php --reset

# Idempotent seed (applies schema CREATE IF NOT EXISTS, then loads fixtures only if empty):
php bin/seed.php
```

The `data/` directory is created automatically. The deterministic fixtures ship with:

* 4 users — `admin` (Admin#12345), `operator` (Operator#12345), `op2`, `viewer`
* 5 services (2 running, 3 stopped)
* 5 approved job profiles
* 4 scheduled jobs with 16 historical job runs
* 4 log files and 160 log entries
* 4 alerts in various states
* 3 health-check targets
* 7 configuration keys
* 1 active API token
* 6 audit events
* 48 metric snapshots across two hosts

### 4. Start the server

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Open `http://localhost:8080/`. You will be redirected to `/login`.

### 5. Sign in

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `Admin#12345` |
| Operator | `operator` | `Operator#12345` |
| Operator | `op2` | `Op2#12345` |
| Operator | `viewer` | `Viewer#12345` |

Admins see the additional menus for **Configuration**, **API tokens**, and **Audit**.

---

## Docker

```bash
docker compose up --build
```

The container:

* Installs PHP 8.3 CLI + `pdo_sqlite`.
* Copies the source and installs Composer dependencies.
* Runs `php bin/seed.php --reset` on first boot (or whenever `DB_RESET_ON_BOOT=1`).
* Starts PHP's built-in server on `0.0.0.0:8080`.

Persistent SQLite files and uploaded backups are stored in the host-mounted `./data/` volume.

To force a fresh database inside the container:

```bash
docker compose exec p16 php bin/seed.php --reset
```

---

## Smoke-check

A PowerShell script exercises every page, API GET, API POST/PATCH, and the operator access controls against a freshly-started server. It exits with the summary report and surfaces any 5xx response in the error log.

```bash
powershell.exe -ExecutionPolicy Bypass -File bin/smoke.ps1
```

The latest local run (PHP 8.3.33, Slim 4.15.2, SQLite 3) returned:

* All 11 admin pages → **200 OK**
* All 12 admin API GETs → **200 OK**
* 17 admin POST/PATCH API calls → **2xx** (the two 422s are correct transition rejections)
* 6 operator privilege probes → **403 forbidden**
* 11 form-driven workflows → **302 redirect**
* Logout + dashboard-after-logout → **302 redirect**
* No fatal errors in the PHP error log

---

## API surface

All API routes return JSON. Authentication is established by the `p16_session` cookie issued on `/login`.

| Method | Path | Use case |
| --- | --- | --- |
| GET / POST / PATCH | `/api/sys/account_access[/{id}]` | SYS-01 |
| GET / POST | `/api/sys/server_dashboard` | SYS-02 |
| GET / POST | `/api/sys/log_viewer` | SYS-03 |
| GET / POST / PATCH | `/api/sys/service_control[/{id}]` | SYS-04 |
| GET / POST / PATCH | `/api/sys/job_scheduler[/{id}]` | SYS-05 |
| GET / PATCH | `/api/sys/job_execution_history[/{id}]` | SYS-06 |
| GET / POST / PATCH | `/api/sys/backup_manager[/{id}]` | SYS-07 |
| GET / POST / PATCH | `/api/sys/configuration_editor[/{id}]` | SYS-08 |
| GET / POST / PATCH | `/api/sys/alert_center[/{id}]` | SYS-09 |
| GET / POST / PATCH | `/api/sys/health_check_targets[/{id}]` | SYS-10 |
| GET / POST / PATCH | `/api/sys/api_token_manager[/{id}]` | SYS-11 |
| GET / POST / PATCH | `/api/sys/audit_logs_and_admin_operations[/{id}]` | SYS-12 |

Roles are enforced:

- `/api/sys/configuration_editor`, `/api/sys/api_token_manager`, and `/api/sys/audit_logs_and_admin_operations` return **403** for non-admin callers.
- `/api/sys/service_control` rejects POST and PATCH by non-admins (operators can only inspect via the page).
- Cross-user ownership is verified for files, jobs, health checks, and job runs.

---

## Use-case traceability matrix

| Use case | Title | Main implementation files / routes |
| --- | --- | --- |
| SYS-01 | Account access | `src/Controllers/AccountAccessController.php`, `templates/login.php`, `templates/register.php`, routes `/login`, `/register`, `/logout`, `/api/sys/account_access[/{id}]`; `src/Services/SessionService.php`; `src/Repositories/UserRepository.php` |
| SYS-02 | Server dashboard | `src/Controllers/ServerDashboardController.php`, `templates/server_dashboard.php`, routes `/dashboard`, `/api/sys/server_dashboard`; `src/Repositories/MetricSnapshotRepository.php` |
| SYS-03 | Log viewer | `src/Controllers/LogViewerController.php`, `templates/log_viewer.php`, routes `/logs`, `/logs/download/{id}`, `/api/sys/log_viewer`; `src/Repositories/LogFileRepository.php`, `LogEntryRepository.php` |
| SYS-04 | Service control | `src/Controllers/ServiceControlController.php`, `templates/service_control.php`, routes `/services`, `/services/{id}/act`, `/api/sys/service_control[/{id}]`; `src/Repositories/ServiceRepository.php` |
| SYS-05 | Job scheduler | `src/Controllers/JobSchedulerController.php`, `templates/job_scheduler.php`, routes `/jobs`, `/jobs/{id}/transition`, `/jobs/{id}/run`, `/api/sys/job_scheduler[/{id}]`; `src/Repositories/ScheduledJobRepository.php`, `JobProfileRepository.php`, `JobRunRepository.php` |
| SYS-06 | Job execution history | `src/Controllers/JobExecutionHistoryController.php`, `templates/job_history_*.php`, routes `/job_runs`, `/job_runs/{id}`, `/job_runs/run/{id}`, `/api/sys/job_execution_history[/{id}]` |
| SYS-07 | Backup manager | `src/Controllers/BackupManagerController.php`, `templates/backup_manager.php`, routes `/backups`, `/backups/download/{id}`, `/backups/{id}/delete`, `/api/sys/backup_manager[/{id}]`; `src/Repositories/StoredFileRepository.php`; storage in `data/storage/backups/` |
| SYS-08 | Configuration editor | `src/Controllers/ConfigurationEditorController.php`, `templates/configuration_editor.php`, routes `/configuration`, `/configuration/{id}/stage`, `/…/approve`, `/…/reject`, `/api/sys/configuration_editor[/{id}]`; `src/Repositories/ConfigurationRepository.php` |
| SYS-09 | Alert center | `src/Controllers/AlertCenterController.php`, `templates/alert_center.php`, routes `/alerts`, `/alerts/{id}`, `/api/sys/alert_center[/{id}]`; `src/Repositories/AlertRepository.php` |
| SYS-10 | Health check targets | `src/Controllers/HealthCheckTargetsController.php`, `templates/health_check_targets.php`, routes `/health_targets`, `/health_targets/{id}/check`, `/…/delete`, `/api/sys/health_check_targets[/{id}]`; `src/Repositories/HealthCheckRepository.php` |
| SYS-11 | API token manager | `src/Controllers/ApiTokenManagerController.php`, `templates/api_token_manager.php`, routes `/api_tokens`, `/api_tokens/{id}/revoke`, `/api/sys/api_token_manager[/{id}]`; `src/Repositories/ApiTokenRepository.php` |
| SYS-12 | Audit logs and admin operations | `src/Controllers/AuditLogsController.php`, `templates/audit_logs.php`, routes `/audit`, `/audit/users`, `/api/sys/audit_logs_and_admin_operations[/{id}]`; `src/Services/AuditService.php`; `src/Repositories/AuditEventRepository.php`, `UserRepository.php` |

---

## Notes and deterministic choices

* All session, audit, alert, configuration, and token records are persisted in SQLite. The session cookie only carries an opaque identifier; the rest of the session payload is server-side.
* Job execution is synchronous and finishes a run within the HTTP request that triggered it (`POST /jobs/{id}/run` or `runNow` from the scheduler). There is no external runner; the run produces deterministic seeded output.
* The TCP probe in `HealthCheckTargetsController::runLocalCheck` performs a real `stream_socket_client` to the configured host:port. HTTP and script kinds report `healthy` deterministically because external HTTP fetching and shell execution are intentionally out of scope.
* Audit events are written for every privileged action (configuration edits, service control transitions, token create/revoke, alert state changes, user management, job scheduler transitions).
* No real email, payment, or storage adapter is contacted. The `alerting.email_enabled` flag exists only to demonstrate the configuration workflow.