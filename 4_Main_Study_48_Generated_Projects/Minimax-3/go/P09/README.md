# P09 — Issue Tracking System

A synthetic, offline-runnable issue tracker aligned with the workflow anchors of lightweight self-hosted forge platforms (projects, issues, comments, labels, milestones, private projects, webhooks, releases, admin settings). Built with Go 1.24, chi, modernc.org/sqlite, and server-rendered HTML templates.

The project delivers all 12 use cases (ISSUE-01 … ISSUE-12) end-to-end, with deterministic seed data, persistent SQLite storage, an embedded WebSocket activity stream, and Docker support. No external services are required after `go mod download`.

---

## Table of contents

- [Technology profile](#technology-profile)
- [Quick start (local)](#quick-start-local)
- [Quick start (Docker)](#quick-start-docker)
- [Configuration](#configuration)
- [Seed accounts](#seed-accounts)
- [Project layout](#project-layout)
- [Use case → implementation traceability](#use-case--implementation-traceability)
- [HTTP routes &amp; events](#http-routes--events)
- [Deterministic seed](#deterministic-seed)
- [Local external integrations](#local-external-integrations)
- [Reset &amp; rebuild](#reset--rebuild)
- [Known limitations](#known-limitations)

---

## Technology profile

The implementation matches the authoritative Go profile:

- Language &amp; runtime: Go 1.24
- HTTP framework: `github.com/go-chi/chi/v5` on the standard `net/http` server
- Database: SQLite via the pure-Go `modernc.org/sqlite` driver (no CGO)
- Authentication: server-side sessions identified by an HTTP-only cookie; session rows persisted in SQLite
- Browser client: server-rendered HTML templates, CSS, and vanilla JavaScript
- Real-time: `github.com/coder/websocket` for the live issue activity stream
- Dependency tooling: Go modules with a committed `go.mod` / `go.sum`
- Configuration: environment variables documented in `.env.example`
- Containerization: multi-stage Dockerfile + compose file

---

## Quick start (local)

```bash
# 1. Install dependencies (uses the cached modules under C:\Users\cheng\go\pkg\mod)
go mod download

# 2. Initialize or reset the SQLite database and apply deterministic seed data
go run ./cmd/seed          # add -reset to wipe data/its.db first

# 3. Build and start the server (migrate + auto-seed run on first start too)
go build -o server ./cmd/server
./server                   # or: go run ./cmd/server
```

Visit <http://localhost:8080/> and sign in with one of the [seed accounts](#seed-accounts) below. The server listens on `APP_ADDR` (default `:8080`).

---

## Quick start (Docker)

```bash
docker compose build
docker compose up -d
docker compose logs -f app
# Stop &amp; remove the data volume:
docker compose down -v
```

The image runs the multi-stage build with `CGO_ENABLED=0`, executes `cmd/seed` once on first start, and then runs `cmd/server`. Data is persisted in the `p09-data` named volume.

---

## Configuration

All runtime values are taken from environment variables. Defaults are shown in `.env.example`.

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_ADDR` | `:8080` | HTTP listen address |
| `APP_BASE_URL` | `http://localhost:8080` | Externally visible base URL |
| `APP_DATA_DIR` | `data` | Persistent data root |
| `APP_DB_PATH` | `data/its.db` | SQLite database file |
| `APP_UPLOAD_DIR` | `data/uploads` | Attachment storage |
| `APP_COOKIE_NAME` | `its_session` | Session cookie name |
| `APP_SESSION_KEY` | (synthetic default) | Reserved for future cookie signing |
| `APP_SKIP_SEED` | (unset) | Set to `1` to disable auto-seed on start |

The server creates `APP_DATA_DIR` and `APP_UPLOAD_DIR` automatically if they do not exist.

---

## Seed accounts

The deterministic seed (`internal/database/seed_data.go`) loads the following users. Passwords are bcrypt-hashed.

| Username | Password | Role |
| --- | --- | --- |
| `alice` | `alicepass1` | admin |
| `bob` | `bobpass123` | maintainer |
| `carol` | `carolpass1` | developer |
| `dave` | `davepass1` | developer |
| `erin` | `erinpass1` | reporter |

Three projects are seeded — `platform-core` and `frontend-app` are public; `internal-roadmap` is private to alice. Each contains six issues with labels, milestones, webhooks, and one initial comment.

---

## Project layout

```
.
├── cmd/
│   ├── server/          # HTTP server entrypoint (cmd/server/main.go)
│   ├── seed/            # Database reset &amp; deterministic seed CLI
│   ├── debug/           # Local migration/seed diagnostic (optional)
│   └── debugtemplate/   # Local template render helper (optional)
├── internal/
│   ├── auth/            # Session store, bcrypt helpers, middleware
│   ├── config/          # Environment variable loader
│   ├── database/        # SQLite open, schema migrations, seed data
│   ├── httpx/           # JSON helpers, API error responses
│   ├── models/          # Domain struct definitions
│   ├── realtime/        # WebSocket hub for live activity
│   ├── templates/       # html/template engine and embedded files/
│   └── modules/
│       ├── account/         # ISSUE-01
│       ├── admin/           # ISSUE-11
│       ├── assignment/      # ISSUE-06
│       ├── attachments/     # ISSUE-07
│       ├── comments/        # ISSUE-05
│       ├── frontend_errors/ # ISSUE-12
│       ├── importexport/    # ISSUE-10
│       ├── issues/          # ISSUE-03
│       ├── private/         # ISSUE-08
│       ├── projects/        # ISSUE-02
│       ├── search/          # ISSUE-04
│       └── webhooks/        # ISSUE-09
├── web/
│   ├── static/
│   │   ├── css/app.css
│   │   ├── js/app.js
│   │   ├── js/issue_ws.js
│   │   └── img/favicon.svg
├── data/                # SQLite DB, uploads, exports (created on demand)
├── docker-compose.yml
├── docker-entrypoint.sh
├── Dockerfile
├── .env.example
├── .gitignore
├── go.mod / go.sum
└── README.md
```

---

## Use case → implementation traceability

| Use case | Title | Primary files | Routes / events |
| --- | --- | --- | --- |
| ISSUE-01 | Account access | `internal/modules/account/handler.go`, `internal/modules/account/pages.go`, `internal/auth/auth.go`, `internal/auth/middleware.go` | `GET /login`, `POST /api/issue/account_access/login`, `POST /api/issue/account_access/register`, `POST /api/issue/account_access/logout`, `POST /api/issue/account_access/profile`, `POST /api/issue/account_access/revoke/{id}`, `GET /account`, `GET /account/access` |
| ISSUE-02 | Project management | `internal/modules/projects/projects.go`, `internal/modules/projects/handler.go` | `GET /projects`, `GET /projects/{slug}`, `GET /projects/new`, `POST /api/issue/project_management`, `POST /api/issue/project_management/{id}` (`_method=PATCH`), `POST /api/issue/project_management/{id}/labels`, `POST /api/issue/project_management/{id}/milestones`, `POST /api/issue/project_management/{id}/members` |
| ISSUE-03 | Issue creation | `internal/modules/issues/issues.go`, `internal/modules/issues/handler.go` | `GET /projects/{slug}/issues/new`, `POST /api/issue/issue_creation/{projectID}` |
| ISSUE-04 | Issue search | `internal/modules/search/search.go` | `GET /search`, `GET /api/issue/issue_search`, `GET /api/issue/issue_search/ws` (WebSocket), `GET /projects/{slug}/search` |
| ISSUE-05 | Comments | `internal/modules/comments/comments.go` | `GET /api/issue/comments`, `POST /api/issue/comments/{issueID}`, `POST /api/issue/comments/{id}/delete` |
| ISSUE-06 | Assignment &amp; workflow | `internal/modules/assignment/assignment.go` | `GET /api/issue/assignment_and_workflow`, `POST /api/issue/assignment_and_workflow/{issueID}` |
| ISSUE-07 | Attachments | `internal/modules/attachments/attachments.go` | `GET /api/issue/attachments`, `POST /api/issue/attachments/{issueID}`, `GET /api/issue/attachments/{id}/download` |
| ISSUE-08 | Private projects | `internal/modules/private/private.go` | `GET /api/issue/private_projects`, `POST /api/issue/private_projects/grant/{projectID}`, `POST /api/issue/private_projects/revoke/{projectID}/{userID}` |
| ISSUE-09 | Webhooks | `internal/modules/webhooks/webhooks.go`, `internal/modules/webhooks/pages.go` | `GET /webhooks/{projectID}`, `GET /api/issue/webhooks`, `POST /api/issue/webhooks/{projectID}`, `POST /api/issue/webhooks/{id}/toggle`, `POST /api/issue/webhooks/{id}/delete` |
| ISSUE-10 | Import / export | `internal/modules/importexport/importexport.go`, `internal/modules/importexport/pages.go` | `GET /import-export`, `GET /api/issue/import_export`, `POST /api/issue/import_export`, `GET /api/issue/import_export/{id}/download` |
| ISSUE-11 | Admin operations | `internal/modules/admin/admin.go`, `internal/modules/admin/pages.go`, `internal/modules/admin/ctx.go` | `GET /admin`, `GET /api/issue/admin_operations`, `POST /api/issue/admin_operations/users/{id}/role`, `POST /api/issue/admin_operations/users/{id}/status`, `POST /api/issue/admin_operations/settings`, `POST /api/issue/admin_operations/transfer` |
| ISSUE-12 | Frontend API integration &amp; errors | `internal/modules/frontend_errors/frontend_errors.go`, `internal/realtime/hub.go`, `web/static/js/app.js`, `web/static/js/issue_ws.js` | `GET /frontend-errors`, `GET /api/issue/frontend_api_integration_and_errors`, `POST /api/issue/frontend_api_integration_and_errors`, WebSocket events: `issue.opened`, `issue.closed`, `issue.reopened`, `issue.commented`, `issue.updated`, `issue.attachment`, `comment.deleted`, `frontend.error` |

All HTML pages live under `internal/templates/files/` and are embedded into the server binary via `//go:embed`.

---

## HTTP routes &amp; events

| Route prefix | Description |
| --- | --- |
| `GET /` | Workspace home with quick stats |
| `GET /login`, `GET /register` | Auth forms |
| `GET /projects`, `GET /projects/{slug}`, `GET /projects/new` | Project pages |
| `GET /projects/{slug}/issues/new`, `GET /projects/{slug}/issues/{number}` | Issue create / detail |
| `GET /projects/{slug}/search` | Redirects to `/search?project={slug}` |
| `GET /search` | Issue search with filters |
| `GET /webhooks/{projectID}` | Webhook management |
| `GET /import-export` | Import / Export UI |
| `GET /admin` | Admin (role-gated) |
| `GET /frontend-errors` | Frontend error audit |
| `GET /account`, `GET /account/access` | Profile &amp; sessions |
| `GET /static/*` | Static assets |
| `GET /api/issue/{module}` | REST API for each module (see traceability) |
| `GET /api/issue/issue_search/ws` | WebSocket live activity stream |

---

## Deterministic seed

The seed file (`internal/database/seed_data.go`) creates:

- 5 users covering admin / maintainer / developer / reporter roles
- 3 workspace settings (`workspace_name`, `allow_registration`, `default_priority`)
- 3 projects (2 public, 1 private) with labels, milestones, members, and one outbound webhook each
- 6 issues per project (18 total) with varied states, priorities, assignees, and labels
- 1 initial comment per open issue

Re-running `go run ./cmd/seed` is idempotent: counts above zero are left untouched.

---

## Local external integrations

| Service | Local adapter |
| --- | --- |
| Email | Not required by any use case |
| Payments | Not required by any use case |
| Object storage | Local filesystem under `data/uploads/` (attachment module) |
| LLM / model responses | Browser-side error reporting (`frontend_api_integration_and_errors`) only |
| Repository integration | Not required |
| External HTTP retrieval | Outbound webhook deliveries (default URL `http://127.0.0.1:65535/hook`); real deliveries use `http.Client` with 10 s timeout |
| Monitoring | Audit log table (`audit_events`) and the WebSocket activity feed |

---

## Reset &amp; rebuild

```bash
# Wipe SQLite file and uploads, then re-seed
go run ./cmd/seed -reset

# Or delete the data directory manually
rm -rf data/

# Rebuild the binaries
go build -o server ./cmd/server
go build -o seed ./cmd/seed
```

Docker equivalent:

```bash
docker compose down -v    # removes the p09-data volume
docker compose up --build
```

---

## Known limitations

- The default outbound webhook URL is `http://127.0.0.1:65535/hook`; deliveries time out against any unreachable host. Add a real listener (e.g. `netcat -l 65535`) or configure your own URL via the webhooks page to observe deliveries.
- Sessions are bound to the HTTP-only cookie `its_session`. There is no CSRF for the WebSocket endpoint; signed origin checking is disabled (`OriginPatterns: ["*"]`) so the included `issue_ws.js` can connect from any origin during local development.
- Import accepts JSON only; CSV export is provided but CSV import is intentionally not supported by the synthetic spec.
- The embedded template engine parses a single page (and its layout) per request to keep `define` blocks scoped; this is intentional and not a performance bottleneck at the benchmark scale.