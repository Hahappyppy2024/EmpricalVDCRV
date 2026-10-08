# P14 — Workflow Automation / Agentic Task Platform

A complete, self-contained web application implementing the **P14 Workflow
Automation / Agentic Task Platform** (synthetic benchmark aligned with
Woodpecker CI's workflow anchors: repositories/pipelines as *workflows*, steps,
webhook triggers, scheduled runs, secrets, logs, replay and templates).

All 12 use cases (AGENT-01 … AGENT-12) are implemented as one integrated
application with shared accounts, sessions, entities and workflows. The system
runs fully offline: every external service (email, HTTP calls, execution engine,
object storage, scheduling) uses deterministic local adapters.

---

## Technology profile (authoritative)

| Concern | Technology |
| --- | --- |
| Language / runtime | Go 1.24 (`net/http`) |
| Router | `github.com/go-chi/chi/v5` |
| Database | SQLite via `modernc.org/sqlite` (pure Go, **no CGO**) |
| Authentication | Server-side sessions, HTTP-only cookie, sessions persisted in SQLite |
| Browser client | Server-served HTML templates, CSS, vanilla JavaScript |
| Real-time | `github.com/coder/websocket` for live run-log streaming |
| Dependency tooling | Go modules (pinned `go.mod` / `go.sum`) |
| Configuration | Environment variables documented in `.env.example` |
| Containerization | Multi-stage `Dockerfile` + `docker-compose.yml` |

---

## Project layout

```
main.go                     entry point, CLI flags (-seed, -version)
internal/config/            environment configuration (.env.example)
internal/db/                SQLite open, schema migration, deterministic seed
internal/models/            domain structs
internal/repo/              data-access layer + resource schema registry
internal/service/           business logic: auth, resources, execution, scheduler
internal/handler/           HTTP router, API handlers, pages, WebSocket
internal/middleware/        session / auth / admin middleware
internal/web/               embedded templates + static assets
Dockerfile, docker-compose.yml, .env.example
```

---

## Prerequisites

- **Go 1.24+** (`go version`). The toolchain is the only install-time
  dependency; the SQLite driver is pure Go, so no CGO/compiler toolchain is
  needed.
- Optional: **Docker** + **Docker Compose** for containerized execution.

## Install dependencies

```bash
go mod download
```

Dependency versions are pinned in `go.mod`; `go.sum` is committed.

## Configuration

Copy the template and adjust if needed (all variables are optional):

```bash
cp .env.example .env     # Windows: copy .env.example .env
```

Defaults already work for local execution. Key variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_ADDR` | `:8080` | HTTP listen address |
| `DB_PATH` | `./data/p14.db` | SQLite database file |
| `SESSION_COOKIE` | `p14_session` | HTTP-only session cookie name |
| `SESSION_TTL_SECONDS` | `86400` | Session lifetime |
| `WEB_BASE_URL` | `http://localhost:8080` | Base URL used in seeds/webhook examples |
| `SEED_DB` | `false` | Reset + reseed on startup |
| `MAX_FILE_BYTES` | `5242880` | Uploaded workspace file limit |

## Database reset / seed

```bash
# Reset the database, apply the schema and load deterministic seed fixtures,
# then start the server.
go run . -seed
# equivalent: set SEED_DB=true
```

Seeding is idempotent: it is skipped automatically when users already exist, so
plain `go run .` is safe to run repeatedly.

### Seed accounts

| Role | Username | Password |
| --- | --- | --- |
| admin | `admin` | `admin123` |
| user | `alice` | `alice123` |
| user | `bob` | `bob123` |

Seed fixtures cover every actor and workflow state: 6 catalog tools (one
non-public), 5 workflows (manual/schedule/webhook/http, including a failing
demo), success + failed runs with logs, schedules (one disabled), webhooks with
tokens, HTTP actions, masked secrets, workspace files, published + unpublished
templates, governance settings and audit events.

## Start the application

```bash
go run .
```

Open <http://localhost:8080> and sign in with a seed account. The scheduler
loop starts automatically (default tick 20 s).

## Docker

```bash
docker compose up --build
# or plain Docker:
docker build -t p14-agentic .
docker run --rm -p 8080:8080 -e SEED_DB=true p14-agentic
```

Open <http://localhost:8080>.

---

## Pages

| URL | Description |
| --- | --- |
| `/login`, `/register` | Sign in / create account (AGENT-01) |
| `/` | Dashboard with per-resource counts and recent runs |
| `/account` | Account access history |
| `/workflows` | Workflow creation (triggers, steps, conditions) |
| `/tools` | Tool catalog (admin defines, users browse visible tools) |
| `/runs` | Task execution — run workflows, inspect status, replay |
| `/runs/{id}` | Run detail with **live WebSocket log stream** + replay |
| `/schedules` | Scheduled runs (5-field cron) |
| `/files` | Workspace files (upload / reference / download) |
| `/webhooks` | Inbound webhook triggers (per-row trigger URL) |
| `/http-actions` | External HTTP actions (create + execute) |
| `/secrets` | Secrets manager (values are stored masked only) |
| `/logs` | Run logs and replay (filters + replay form) |
| `/templates` | Sharing and templates (publish + downloads) |
| `/admin` | Admin governance (settings, users, quotas, audit, scheduler) |

## JSON API contract

Every resource follows the same contract (authenticated unless noted):

```
GET    /api/agent/{resource}[?filter=value&q=search&limit=&offset=]
POST   /api/agent/{resource}
GET    /api/agent/{resource}/{id}
PATCH  /api/agent/{resource}/{id}
DELETE /api/agent/{resource}/{id}
```

Supported `{resource}` names: `account_access`, `workflow_creation`,
`tool_catalog`, `task_execution`, `scheduled_runs`, `workspace_files`,
`webhook_triggers`, `external_http_action`, `secrets_manager`,
`run_logs_and_replay`, `sharing_and_templates`, `admin_governance`.

Special endpoints:

```
POST /api/agent/webhooks/{token}/trigger            public inbound webhook trigger (AGENT-07)
POST /api/agent/external_http_action/{id}/execute   call endpoint, record result (AGENT-08)
GET  /api/agent/workspace_files/{id}/download       download stored file (AGENT-06)
POST /api/agent/task_execution/{id}/replay          re-run a workflow (AGENT-10)
POST /api/agent/run_logs_and_replay/{id}/replay     alias for replay
POST /api/agent/sharing_and_templates/{id}/publish  publish template (AGENT-11)
GET  /api/agent/admin_governance/users              admin: list users (AGENT-12)
GET  /api/agent/admin_governance/audit              admin: audit events (AGENT-12)
POST /api/agent/admin_governance/users/{id}         admin: enable/disable, change role (AGENT-12)
POST /api/agent/admin_governance/run-scheduler      admin: evaluate due schedules now (AGENT-05)
GET  /internal/echo                                 deterministic local adapter for HTTP actions (AGENT-08)
GET  /api/agent/ws/runs/{id}                        WebSocket live run-log stream (AGENT-10)
```

Ownership and visibility rules are enforced server-side: users only see their
own records, private catalog tools never appear to non-admins, secrets are only
ever returned masked, and governance settings / user management require the
admin role.

## Example curl flows

```bash
# Sign in (writes the session cookie into cookies.txt)
curl -c cookies.txt -H "Content-Type: application/json" \
  -d '{"username":"alice","password":"alice123"}' http://localhost:8080/login

# List your workflows
curl -b cookies.txt http://localhost:8080/api/agent/workflow_creation

# Run workflow 1
curl -b cookies.txt -H "Content-Type: application/json" \
  -d '{"workflow_id":1,"trigger":"manual"}' http://localhost:8080/api/agent/task_execution

# Trigger an inbound webhook (public, no session needed)
curl -X POST http://localhost:8080/api/agent/webhooks/wh_deploy_alice/trigger

# Execute an HTTP action against the offline echo adapter
curl -b cookies.txt -X POST http://localhost:8080/api/agent/external_http_action/1/execute

# Replay a run
curl -b cookies.txt -X POST http://localhost:8080/api/agent/task_execution/1/replay
```

---

## Use-case traceability

| Use case | Title | Main files | Routes / events |
| --- | --- | --- | --- |
| AGENT-01 | Account access | `internal/service/auth.go`, `internal/handler/pages.go`, `internal/db/seed.go` | `POST /login`, `POST /register`, `POST /logout`, `GET/POST /api/agent/account_access`, sessions in `internal/repo/repo.go` |
| AGENT-02 | Workflow creation | `internal/service/resources.go` (workflow_creation), `internal/db/seed.go` | `GET/POST/PATCH/DELETE /api/agent/workflow_creation`, page `/workflows` |
| AGENT-03 | Tool catalog | `internal/service/resources.go` (tool_catalog), `internal/db/seed.go` | `GET/POST/PATCH /api/agent/tool_catalog`, page `/tools` |
| AGENT-04 | Task execution | `internal/service/execution.go`, `internal/service/resources.go` | `POST /api/agent/task_execution`, page `/runs`, detail `/runs/{id}` |
| AGENT-05 | Scheduled runs | `internal/service/scheduler.go`, `internal/service/resources.go` | `GET/POST/PATCH /api/agent/scheduled_runs`, `POST /api/agent/admin_governance/run-scheduler`, scheduler goroutine in `main.go` |
| AGENT-06 | Workspace files | `internal/service/resources.go` (workspace_files), `internal/handler/api.go` | `GET/POST/PATCH /api/agent/workspace_files`, `GET /api/agent/workspace_files/{id}/download`, page `/files` |
| AGENT-07 | Webhook triggers | `internal/service/resources.go`, `internal/service/execution.go` (TriggerWebhook) | `GET/POST/PATCH /api/agent/webhook_triggers`, `POST /api/agent/webhooks/{token}/trigger`, page `/webhooks` |
| AGENT-08 | External HTTP action | `internal/service/execution.go` (ExecuteHTTPAction, httpStep), `internal/handler/handler.go` (echo) | `GET/POST/PATCH /api/agent/external_http_action`, `POST /api/agent/external_http_action/{id}/execute`, `GET /internal/echo`, page `/http-actions` |
| AGENT-09 | Secrets manager | `internal/service/resources.go` (secrets_manager, mask) | `GET/POST/PATCH /api/agent/secrets_manager`, page `/secrets` |
| AGENT-10 | Run logs and replay | `internal/service/execution.go` (RunDetail, ReplayRun), `internal/handler/ws.go` | `GET/POST/PATCH /api/agent/run_logs_and_replay`, `POST /api/agent/task_execution/{id}/replay`, `GET /api/agent/ws/runs/{id}`, pages `/logs`, `/runs/{id}` |
| AGENT-11 | Sharing and templates | `internal/service/resources.go` (sharing_and_templates, PublishTemplate) | `GET/POST/PATCH /api/agent/sharing_and_templates`, `POST /api/agent/sharing_and_templates/{id}/publish`, page `/templates` |
| AGENT-12 | Admin governance | `internal/service/resources.go` (admin_governance, ListUsers, UpdateUserAdmin, ListAudit) | `GET/POST/PATCH /api/agent/admin_governance`, `GET /api/agent/admin_governance/users`, `/audit`, `POST /api/agent/admin_governance/users/{id}`, page `/admin` |

---

## Determinism notes

- The execution engine runs every workflow step through deterministic local
  adapters (`[runner]`/`[http]`/`[file-io]`/`[secret-lookup]`/`[notify]`); a
  step whose `"fail": true` flag is set fails deterministically and the
  remaining steps are marked `skipped`.
- External HTTP actions point at the built-in `/internal/echo` adapter, which
  returns a stable JSON response offline.
- Secrets are persisted **masked only**; the raw value is never returned by any
  API.
- Cron parsing supports `*`, `*/N`, `N` and comma lists for minute/hour; day,
  month and weekday fields must be `*`.
- Repeated creates are idempotent where a natural key exists (workflow
  name+owner, secret name+owner, webhook token, tool name).

## Smoke-check summary

Verified against a locally seeded instance:

- sign-in / registration / sign-out, cookie sessions
- every resource list / create / update / delete route
- manual run execution with step-by-step logs, failed-run simulation, replay
- scheduled run fired automatically by the scheduler loop
- public webhook trigger, HTTP action execute against the echo adapter
- multipart workspace file upload + download, cross-user access rejected
- tool-catalog visibility filtering, masked secrets
- governance: disable-action rejection, per-user quotas, user/role management,
  audit trail
- WebSocket handshake (101) and live log stream
- all browser pages return 200; `/admin` returns 403 for non-admins

---

## Functional and security tests

The bundled `tests/function` suite has one dedicated Go test for each use case
AGENT-01 through AGENT-12. The `tests/exploit` suite contains 30 executable
PowerShell HTTP attack scenarios grouped by OWASP A01-A10 and CWE, using the
same Core/P14-specific structure as the supplied reference suite.

```powershell
.\tests\run_function_tests.ps1
.\tests\run_function_coverage.ps1

# In terminal 1, reset and start a disposable seeded target:
go run . -seed

# In terminal 2:
.\tests\run_security_tests.ps1
```

Security `FAIL` means the observed response violated that scenario's secure
oracle and should be manually replayed as a candidate vulnerability. See
`tests/README.md`, `tests/FUNCTION_TEST_MATRIX.md`, and
`tests/exploit/SECURITY_ORACLE.md` for interpretation details.
