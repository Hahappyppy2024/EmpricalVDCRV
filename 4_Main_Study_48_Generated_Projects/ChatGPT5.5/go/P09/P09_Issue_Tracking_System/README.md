# P09 — Issue Tracking System (Go)

A synthetic, offline-capable issue tracking web application generated from the authoritative P09 Go specification.

## Technology

- Go 1.24
- `github.com/go-chi/chi/v5`
- SQLite with pure-Go `modernc.org/sqlite` (no CGO)
- Server-side persistent sessions in SQLite with HTTP-only cookies
- Server-served HTML + vanilla browser UI
- Go modules
- Multi-stage Docker build + Docker Compose

## Seed accounts

| Actor | Email | Password |
|---|---|---|
| Admin | `admin@example.test` | `AdminPass123!` |
| Project owner | `owner@example.test` | `OwnerPass123!` |
| Project member | `member@example.test` | `MemberPass123!` |
| Outsider | `outsider@example.test` | `OutPass123!` |

Seed project `Alpha` is private. Owner and member are project members; outsider is not.

## Local setup

```bash
cp .env.example .env
go mod download
go run ./cmd/dbreset
go run ./cmd/server
```

Open `http://localhost:8080`.

## Functional tests

Each use case has one dedicated functional test file:

```bash
go test ./tests/function -v
```

Coverage:

```bash
./scripts/coverage.sh
```

The coverage runner reports **native Go line/statement coverage** and a separate **instrumented branch-outcome coverage** measurement. The default 85% threshold applies only to the measured branch-outcome percentage; it never changes the measured value. See `BRANCH_COVERAGE_METHOD.md` for the exact metric definition.

On Windows PowerShell:

```powershell
.\scripts\coverage.ps1
```

Generated artifacts include `coverage/line.html`, `coverage/line.txt`, `coverage/branch.json`, and `coverage/branch.txt`.

## Docker

```bash
docker compose up --build
```

The container runs the deterministic DB reset/seed command before starting the web server. Persistent DB and attachment storage are backed by named Docker volumes.

To stop:

```bash
docker compose down
```

To reset volumes completely:

```bash
docker compose down -v
```

## HTTP smoke checks

After startup:

```bash
curl -i http://localhost:8080/
curl -i http://localhost:8080/login
curl -i http://localhost:8080/issues
```

All three browser pages should return HTTP 200.

## Use-case traceability

| ID | Main implementation / routes | Functional test |
|---|---|---|
| ISSUE-01 | auth handlers in `internal/app/app.go`; `/api/auth/*` | `issue01_account_access_test.go` |
| ISSUE-02 | project/member handlers; `/api/projects*` | `issue02_project_management_test.go` |
| ISSUE-03 | issue CRUD handlers | `issue03_issue_creation_test.go` |
| ISSUE-04 | `GET /api/issues` structured search | `issue04_issue_search_test.go` |
| ISSUE-05 | comments handlers | `issue05_comments_test.go` |
| ISSUE-06 | assignee + transition handlers | `issue06_assignment_workflow_test.go` |
| ISSUE-07 | attachment storage handlers | `issue07_attachments_test.go` |
| ISSUE-08 | project visibility/list/detail handlers | `issue08_private_projects_test.go` |
| ISSUE-09 | webhook handlers + local delivery records | `issue09_webhooks_test.go` |
| ISSUE-10 | transactional import + CSV export | `issue10_import_export_test.go` |
| ISSUE-11 | admin users/labels/audit handlers | `issue11_admin_operations_test.go` |
| ISSUE-12 | browser pages + stable API success/error states | `tests/function/issue12_frontend_api_integration_errors_test.go` |

## Security tests

PowerShell HTTP security tests are under `tests/exploit`, grouped by OWASP Top 10 category and CWE. Start the seeded server, then run:

```powershell
.\tests\run_security_tests.ps1
```

The runner writes JSON reports under `tests/exploit/reports`. `FAIL` means a candidate security finding that should be manually replayed; it is not hidden or converted to a passing result.

## Contract count

The specification contains 40 use-case API-contract references and 39 unique HTTP method/path routes because `POST /api/projects` is shared by ISSUE-02 and ISSUE-08.

## Validation status in the packaging environment

The package structure, JSON manifests, shell syntax, use-case count, and test-path references were checked statically. This packaging environment does not provide Go, PowerShell, or Docker, so `go test`, the PowerShell exploit runner, Docker build, and numeric coverage measurement were not executed here. Run them in a Go 1.24 and PowerShell-capable environment. No unexecuted coverage percentage is claimed.
