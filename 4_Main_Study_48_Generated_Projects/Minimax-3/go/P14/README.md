# P14 — Workflow Automation / Agentic Task Platform

A synthetic, offline-runnable benchmark implementation of a workflow automation
and agentic task platform. It is implemented entirely in Go 1.24 and aligns
broadly with the workflow anchors exposed by Woodpecker CI (repositories,
pipelines, workflow steps, webhook triggers, scheduled runs, secrets,
runners/agents, logs, replay, and templates) without copying any project-specific
behaviour or CVE details.

> **Status:** application generation only. No tests, attack scripts, or benchmark
> oracles are generated alongside this project.

---

## Stack

| Concern             | Choice                                                              |
| ------------------- | ------------------------------------------------------------------- |
| Language / runtime  | Go 1.24                                                             |
| Web framework       | `github.com/go-chi/chi/v5` over `net/http`                          |
| Database driver     | `modernc.org/sqlite` (pure Go, **no CGO required**)                 |
| Browser client      | Server-rendered HTML templates, vanilla CSS, vanilla JavaScript      |
| Real-time transport | `github.com/coder/websocket` for live runner event streaming        |
| Session storage     | HTTP-only cookies signed by `golang.org/x/crypto/hmac`              |
| Local file storage  | On-disk under `data/workspaces/<user>/...` and `data/uploads/...`    |
| External HTTP       | Deterministic loopback-only outbound fetches                        |
| Dependency tooling  | Go modules with a committed `go.mod` / `go.sum`                     |

---

## Prerequisites

* Go 1.24 or newer (only for `go run` / `go build` workflows).
* Docker (only for the optional containerized workflow).
* A POSIX-ish shell (PowerShell on Windows works for the commands shown).

No paid services, external accounts, or network access are required at runtime.

---

## Quick start

```bash
# 1. fetch dependencies (uses the public proxy by default; cache-friendly)
go mod download

# 2. build a single static binary
go build -o p14-server .

# 3. (optional) wipe and re-seed the SQLite database on the next start
./p14-server -reset

# 4. start the server (default address: :8080)
./p14-server
```

The first launch (and every `-reset` launch) creates `data/app.db` and populates
the deterministic fixtures described in **Seed accounts** below.

### Seed accounts

| Role  | Email                | Password   |
| ----- | -------------------- | ---------- |
| admin | `admin@p14.local`    | `admin123` |
| user  | `alice@p14.local`    | `user123`  |

### Configuration

Every environment variable is documented in [`.env.example`](./.env.example).
Override any value with shell-level variables before invoking the binary, e.g.:

```bash
HTTP_ADDR=:9000 SESSION_SECRET=$(openssl rand -hex 32) ./p14-server
```

### Database commands

| Goal                             | Command                                                    |
| -------------------------------- | ---------------------------------------------------------- |
| Initialise / re-seed             | `./p14-server -reset`                                      |
| Inspect schema (uses sqlite3)    | `sqlite3 data/app.db ".tables"`                            |
| Inspect seeded workflows         | `sqlite3 data/app.db "SELECT id, name FROM workflow_creation;"` |

The repository ships an `internal/database/schema.sql` that is applied on every
startup. The schema is also embedded via `//go:embed` so the binary is fully
self-contained.

---

## Docker

```bash
# build and start
docker compose up --build

# wipe data and start fresh
docker compose down -v
docker compose up --build
```

The compose file mounts a named volume at `/app/data` so the SQLite database
and on-disk workspace files survive container restarts. The Dockerfile is a
two-stage build that emits a distroless static image.

---

## Project layout

```
.
├── main.go                     # entry point, router wiring, lifecycle
├── go.mod / go.sum             # pinned module versions
├── Dockerfile / docker-compose.yml
├── .env.example                # documented configuration values
├── internal/
│   ├── config/                 # env-driven configuration
│   ├── database/               # SQLite open + deterministic seed fixtures
│   ├── models/                 # domain structs
│   ├── auth/                   # bcrypt, session cookies, secret encryption
│   ├── middleware/             # auth + recoverer
│   ├── repo/                   # data-access layer per aggregate
│   ├── runner/                 # workflow execution engine + pub/sub bus
│   ├── handlers/               # HTTP / WebSocket handlers per use case
│   ├── httpx/                  # JSON response helpers
│   └── render/                 # embedded html/template renderer (templates/ + funcs)
└── web/
    └── static/                 # css/ + js/ (served from /static)
```

---

## Implemented modules

| Module                            | Source files                                                                                                  |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Account access                    | `internal/handlers/handlers.go`, `internal/auth/auth.go`, `internal/middleware/auth.go`                      |
| Workflow creation                 | `internal/handlers/handlers.go`, `internal/repo/workflows.go`                                                |
| Tool catalog                      | `internal/handlers/handlers.go`, `internal/repo/tools.go`                                                    |
| Task execution                    | `internal/handlers/handlers.go`, `internal/runner/runner.go`, `internal/repo/runs.go`                         |
| Scheduled runs                    | `internal/handlers/handlers.go`, `internal/repo/schedules.go`                                                |
| Workspace files                   | `internal/handlers/handlers.go`, `internal/repo/files.go`                                                    |
| Webhook triggers                  | `internal/handlers/handlers.go`, `internal/repo/webhooks.go`                                                 |
| External HTTP action              | `internal/handlers/handlers.go`, `internal/repo/http_actions.go`                                            |
| Secrets manager                   | `internal/handlers/handlers.go`, `internal/repo/secrets.go`, `internal/auth/auth.go`                          |
| Run logs &amp; replay             | `internal/handlers/handlers.go`, `internal/repo/logs.go`                                                     |
| Sharing &amp; templates           | `internal/handlers/handlers.go`, `internal/repo/templates.go`                                                |
| Admin governance                  | `internal/handlers/handlers.go`, `internal/repo/admin.go`, `internal/repo/users.go`                           |
| Live event stream                 | `internal/handlers/ws.go`, `internal/runner/runner.go`                                                        |

---

## Use-case → implementation traceability

| Use case  | Routes / endpoints                                                          | Page                                       | Handler                                  |
| --------- | --------------------------------------------------------------------------- | ------------------------------------------ | ---------------------------------------- |
| AGENT-01  | `GET/POST /account_access/sign_in`, `…/register`, `…/sign_out`, `…/reset`   | `pages/account.html` (+ sign-in / register) | `Deps.Account*`                          |
| AGENT-02  | `GET/POST /workflow_creation`, `POST /workflow_creation/{id}/run|archive`    | `pages/workflow_creation.html`             | `Deps.WorkflowsPage`, `WorkflowRunPost`  |
| AGENT-03  | `GET/POST /tool_catalog`, `POST /tool_catalog/{id}/toggle`                   | `pages/tool_catalog.html`                  | `Deps.ToolCatalogPage`, `ToolTogglePost` |
| AGENT-04  | `GET/POST /task_execution`, `GET /ws/runs/{id}`                              | `pages/task_execution.html`                | `Deps.TaskExecutionPage`, `RunStreamWS`  |
| AGENT-05  | `GET/POST /scheduled_runs`, `POST /scheduled_runs/{id}/toggle|delete`       | `pages/scheduled_runs.html`                | `Deps.SchedulesPage`                     |
| AGENT-06  | `GET/POST /workspace_files`, `POST /workspace_files/upload`, `…/view`, `…/{id}/delete` | `pages/workspace_files.html`      | `Deps.WorkspacePage`, `WorkspaceUploadPost` |
| AGENT-07  | `GET/POST /webhook_triggers`, `POST /webhook_triggers/{id}/revoke`, `POST /webhooks/invoke`, `POST /api/webhooks/{token}` | `pages/webhook_triggers.html` | `Deps.WebhooksPage`, `WebhookPublicInvoke` |
| AGENT-08  | `GET/POST /external_http_action`                                            | `pages/external_http_action.html`          | `Deps.HTTPPage`                          |
| AGENT-09  | `GET/POST /secrets_manager`, `POST /secrets_manager/{id}/revoke`             | `pages/secrets_manager.html`               | `Deps.SecretsPage`, `SecretRevokePost`   |
| AGENT-10  | `GET /run_logs_and_replay`, `POST /run_logs_and_replay/replay`               | `pages/run_logs_and_replay.html`           | `Deps.RunLogsPage`                       |
| AGENT-11  | `GET/POST /sharing_and_templates`, `POST /sharing_and_templates/{id}/visibility|delete` | `pages/sharing_and_templates.html` | `Deps.TemplatesPage`                     |
| AGENT-12  | `GET /admin_governance`, `POST /admin_governance/{key}/update`, `POST /admin_governance/users/{id}/toggle_status|toggle_role` | `pages/admin_governance.html` | `Deps.AdminPage`, `Admin*` |

Additional utility routes:

| Path                | Purpose                                              |
| ------------------- | ---------------------------------------------------- |
| `GET /`             | Marketing / landing page                             |
| `GET /dashboard`    | Per-user roll-up of workflows / runs / schedules     |
| `GET /api/health`   | Health probe                                         |
| `POST /api/echo`    | Loopback echo used by the `http_get` tool in tests   |
| `GET /static/...`   | CSS + JavaScript assets                              |

---

## Functional acceptance walkthrough

| Criterion                  | How to verify                                                                                      |
| -------------------------- | -------------------------------------------------------------------------------------------------- |
| AGENT-01-FA-01             | Sign in as `alice@p14.local` / `user123` and reach `/dashboard`.                                    |
| AGENT-01-FA-02             | Submit the sign-in form with a wrong password → page renders a stable error.                       |
| AGENT-01-FA-03             | Click *Sign out* → private pages redirect to the sign-in page.                                     |
| AGENT-02-FA-01             | Create a workflow from the *Workflows* page; refresh to see it in the table.                       |
| AGENT-02-FA-02             | Submit the form with a blank name → form re-renders with an error, nothing is persisted.           |
| AGENT-02-FA-03             | A different user attempting to run a workflow they do not own receives a 403.                      |
| AGENT-03-FA-01 / FA-02 / FA-03 | Filter the tool catalog by name; admin can toggle availability while regular users cannot.     |
| AGENT-04-FA-01 / FA-02 / FA-03 | Queue a run for an existing workflow; status transitions to *success*; foreign workflows reject. |
| AGENT-05-FA-01 / FA-02 / FA-03 | Create a schedule; toggle/delete; cross-user attempts return 403.                              |
| AGENT-06-FA-01 / FA-02 / FA-03 | Create / update a workspace file; delete; attempting to view another user's file returns 404.   |
| AGENT-07-FA-01 / FA-02 / FA-03 | Create a webhook; revoke it; invoke via the public URL; revocations stop invocations.            |
| AGENT-08-FA-01 / FA-02 / FA-03 | POST a loopback URL through the external HTTP action page; non-loopback URLs are rejected.       |
| AGENT-09-FA-01 / FA-02 / FA-03 | Store a secret, observe the masked value, revoke it.                                              |
| AGENT-10-FA-01 / FA-02 / FA-03 | Filter logs by run id / level; replay a run to append new entries; foreign logs are not visible.|
| AGENT-11-FA-01 / FA-02 / FA-03 | Publish a template; visibility flips from *private* to *shared*; cross-user templates are hidden. |
| AGENT-12-FA-01 / FA-02 / FA-03 | As admin toggle a quota value, disable a user, promote a user; each writes to the audit log.       |

---

## Operational notes

* The SQLite database uses WAL mode and a shared in-process cache so multiple
  readers do not block writers.
* Sessions are stored in the `sessions` table and expire automatically after
  `SESSION_LIFETIME_MIN`; the server runs a background cleanup every hour.
* The runner executes workflow steps sequentially per run, emits structured
  events on the in-process bus, and persists every step into the
  `task_steps` and `run_logs_and_replay` tables.
* The runner's `shell` tool is disabled by default. Set `P14_ALLOW_SHELL=true`
  to enable a sandboxed `/bin/echo`-style command helper.
* The `http_get` / `http_post_json` tools restrict outbound calls to loopback
  addresses to keep the synthetic benchmark fully offline.

---

## License

This project is a synthetic benchmark generated for evaluation purposes. No
upstream project source code was copied; the high-level workflow anchors are
inspired by Woodpecker CI but the implementation is independent.