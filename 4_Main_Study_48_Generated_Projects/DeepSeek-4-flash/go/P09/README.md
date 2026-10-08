# P09 — Issue Tracking System (Go)

A complete, runnable issue tracking web application generated from the P09
specification (Version A). It mirrors the high-level workflow categories of the
real-world alignment target (Gitea) — projects, issues, comments, labels,
milestones, private projects, webhooks, releases-like milestones, and admin
settings — as a synthetic benchmark system.

## Technology stack

| Concern | Choice |
| --- | --- |
| Language / runtime | Go 1.24 |
| Web framework | `github.com/go-chi/chi/v5` on `net/http` |
| Database | SQLite via pure-Go `modernc.org/sqlite` (no CGO) |
| Authentication | Server-side sessions, HTTP-only cookie, persistent session rows in SQLite |
| Browser client | Server-served HTML templates, CSS, vanilla JavaScript |
| Real-time | `github.com/coder/websocket` (`/ws/projects/{id}` issue event stream) |
| Dependency tooling | Go modules with committed `go.mod` / `go.sum` |
| External services | Deterministic local adapters (webhook delivery via HTTP with recorded attempts; no paid services) |

## Prerequisites

- Go 1.24+ (https://go.dev/dl/)
- Docker + Docker Compose (optional, for containerized execution)

## Setup

```bash
# 1. Install dependencies
go mod download

# 2. Copy environment configuration (optional; defaults match .env.example)
copy .env.example .env
```

Configuration variables (all documented in `.env.example`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `8080` | HTTP listen port |
| `DB_PATH` | `data/issuetracker.db` | SQLite database file |
| `UPLOAD_DIR` | `data/uploads` | Attachment/import storage |
| `SESSION_SECRET` | dev value | Session secret (change in production) |
| `SESSION_TTL_HOURS` | `24` | Session lifetime |
| `MAX_UPLOAD_MB` | `5` | Upload size limit |
| `APP_URL` | `http://localhost:8080` | Public base URL (seeded webhook URLs) |

## Database reset / seed

```bash
go run . -reset
```

Drops all tables and seeds deterministic fixtures: users, projects (public and
private), labels, milestones, issues in every workflow state, comments,
memberships, webhooks (pointing at the local listener), audit events and
per-use-case workflow records. The database is created and seeded
automatically on first start if it does not exist.

Seed accounts — **password for all: `password123`**

| Username | Role | Notes |
| --- | --- | --- |
| `admin` | admin | full admin operations |
| `maintainer` | maintainer | project/issue management |
| `dev` | developer | creates issues, is member of `secret-sauce` |
| `reporter` | reporter | files issues |
| `member` | member | member of the private project `secret-sauce` |
| `outsider` | developer | NOT a member of `secret-sauce` (negative access checks) |

Seed projects: `web-platform` (public), `mobile-app` (public),
`secret-sauce` (private; only admin, maintainer, dev, member may see it).

## Start

```bash
go run .              # or: go build -o issuetracker.exe . && .\issuetracker.exe
```

Open http://localhost:8080 — sign in with any seed account.

## Docker

```bash
docker compose up --build
# then open http://localhost:8080
```

Resetting the database inside Docker:

```bash
docker compose exec issuetracker /app/issuetracker -reset
docker compose restart issuetracker
```

## Feature map

- **Account access** — register, sign in, sign out, profile editing; session
  records persisted in SQLite; sign-out invalidates the session so private
  pages are no longer reachable.
- **Project management** — maintainers/admins create projects (public or
  private), labels, milestones, visibility switches; audited.
- **Issue creation** — title, body, priority, labels.
- **Issue search** — text + filters (status, priority, project, assignee,
  label); private records never leak to non-members.
- **Comments** — add, edit, delete (author or maintainer).
- **Assignment and workflow** — assignee, milestone and status transitions
  (`open → in_progress → resolved → closed`, reopen allowed; invalid
  transitions are rejected).
- **Attachments** — type/size-validated uploads stored under `UPLOAD_DIR` with
  SHA-256 metadata; downloads enforce project access.
- **Private projects** — membership-gated visibility; member management.
- **Webhooks** — outbound HTTP webhooks with HMAC-SHA256 signatures; every
  delivery attempt recorded; seeded webhooks target the local listener
  `POST /api/issue/webhooks/listener` so everything works offline.
- **Import/export** — CSV issue import and CSV project report export.
- **Admin operations** — user role/active state, project ownership transfer,
  global settings; all privileged actions audited.
- **Frontend API integration and errors** — the JS client surfaces validation,
  permission, webhook and upload errors as toasts and records them via
  `POST /api/issue/frontend_api_integration_and_errors`.

## API contract (per use case)

Every use case exposes `GET /api/issue/<name>`, `POST /api/issue/<name>` and
`PATCH /api/issue/<name>/{id}` returning
`{"ok":true,"data":…}` or `{"ok":false,"error":{"code":…,"message":…}}` with
deterministic error codes (`validation_failed`, `unauthorized`, `forbidden`,
`not_found`, `invalid_transition`, `file_too_large`, `file_type_not_allowed`,
`no_file`, `storage_unavailable`, `duplicate`, `network_error`). API routes
require an active session (401 otherwise).

## Design decisions (deterministic choices)

- The per-use-case entities required by the specification
  (`account_access`, `project_management`, `issue_creation`, `issue_search`,
  `comments`, `assignment_and_workflow`, `attachments`, `private_projects`,
  `webhooks`, `import_export`, `admin_operations`,
  `frontend_api_integration_and_errors`) are persisted as workflow record
  tables; the domain tables (`projects`, `issues`, `labels`, `milestones`,
  `stored_files`, `webhook_deliveries`, `audit_events`) hold authoritative
  data. The `comments` table is used both as domain data and as the ISSUE-05
  entity.
- Registration always creates the `developer` role; other roles exist as seed
  accounts or are granted by admins.
- Webhook seed URLs are built from `APP_URL` and point at the local listener
  endpoint.
- Status transitions follow the allowed-transition matrix in
  `internal/models` (closed → in_progress / resolved are rejected).
- Search results are bounded to 100 records by default (max 500).

## Traceability table

| Use case | Main implementation files / routes |
| --- | --- |
| ISSUE-01 Account access | `internal/web/auth.go`, `internal/auth/session.go`, `internal/store/users.go`; `/login`, `/register`, `/logout`, `/profile`, `/api/issue/account_access` |
| ISSUE-02 Project management | `internal/web/projects.go`, `internal/store/projects.go`; `/projects*`, `/api/issue/project_management` |
| ISSUE-03 Issue creation | `internal/web/issues.go`, `internal/store/issues.go`; `/projects/{slug}/issues/new`, `/api/issue/issue_creation` |
| ISSUE-04 Issue search | `internal/web/issues.go` (`pageSearch`, `issueSearchAPI`); `/search`, `/api/issue/issue_search` |
| ISSUE-05 Comments | `internal/web/issues.go` (`commentsAPI`), `web/templates/issue.html`; `/issues/{id}/comments`, `/api/issue/comments` |
| ISSUE-06 Assignment and workflow | `internal/web/issues.go` (`assignmentWorkflowAPI`), `internal/models` (transitions); `/api/issue/assignment_and_workflow` |
| ISSUE-07 Attachments | `internal/web/issues.go` (`attachmentsAPI`, `fileDownload`), `internal/store/files_webhooks.go`; `/api/issue/attachments`, `/api/files/{id}/download` |
| ISSUE-08 Private projects | `internal/web/projects.go` (`privateProjectsAPI`, member handlers), `internal/store/projects.go`; `/api/issue/private_projects` |
| ISSUE-09 Webhooks | `internal/web/webhooks.go`, `internal/service/webhook.go`, `internal/store/files_webhooks.go`; `/api/issue/webhooks`, `/api/issue/webhooks/listener` |
| ISSUE-10 Import/export | `internal/web/transfer_admin.go` (`importExportAPI`, `exportReport`); `/api/issue/import_export` |
| ISSUE-11 Admin operations | `internal/web/transfer_admin.go` (`adminOperationsAPI`), `internal/store/users.go`; `/admin`, `/api/issue/admin_operations` |
| ISSUE-12 Frontend API integration and errors | `internal/web/transfer_admin.go` (`frontendErrorsAPI`), `web/static/js/app.js`; `/api/issue/frontend_api_integration_and_errors` |

## Smoke-check

```bash
curl http://localhost:8080/healthz
# {"ok":true,"service":"p09-issue-tracking-system"}
```

## Functional and security tests

The complete test suite is under `tests/`:

```powershell
.\tests\run_function_tests.ps1
.\tests\run_function_coverage.ps1
.\tests\run_security_tests.ps1
```

There are 12 dedicated functional test files (one for each ISSUE-01 through
ISSUE-12) plus shared test infrastructure. The security suite contains 30
PowerShell HTTP scenarios classified by OWASP folder/CWE and by reusable
`Core` versus `P09_Specific` layer. See `tests/README.md`,
`tests/FUNCTION_TEST_MATRIX.md`, and `tests/exploit/SECURITY_ORACLE.md` before
interpreting results.
