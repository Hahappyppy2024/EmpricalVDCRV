# P08 — Enterprise Expense Approval System

A complete, runnable enterprise expense approval web application generated from the P08 specification (JavaScript profile). Employees create expense reports with line items, attach receipts, submit them for approval; managers approve or reject; finance reviews and exports reimbursement batches; admins configure departments, cost centers, categories, roles, and policy rules. All business data is persisted in SQLite and served through a deterministic local stack.

## Technology stack

- Language: JavaScript (ECMAScript modules)
- Runtime: Node.js 22 LTS
- Web framework: Express 5
- Database: SQLite via `better-sqlite3` (repository/data-access layer under `src/db` / `src/services`)
- Authentication: server-side sessions in SQLite with an HTTP-only cookie (`exp_session`)
- Browser client: server-served HTML, CSS, and vanilla JavaScript (no build step)
- Real-time transport: `ws` WebSocket channel at `/ws` for comment and approval events
- Tooling: npm with committed `package-lock.json` and pinned direct dependency versions

## Prerequisites

- Node.js 22 LTS or newer
- npm 10+ (bundled with Node 22)
- Docker + Docker Compose (optional, for containerized execution)

## Dependencies (pinned)

Installed via `package-lock.json`:

| Package | Version |
| --- | --- |
| express | 5.2.1 |
| better-sqlite3 | 13.0.3 |
| ws | 8.21.3 |
| multer | 2.2.0 |

## Setup

```bash
npm install
```

Copy the environment template and adjust if needed:

```bash
cp .env.example .env
```

The defaults in `.env.example` work out of the box (port 3000, local SQLite at `data/app.db`, local uploads at `data/uploads`). No external services or accounts are required.

### Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `PORT` | `3000` | HTTP port |
| `HOST` | `0.0.0.0` | Bind address |
| `DB_PATH` | `./data/app.db` | SQLite database file |
| `DATA_DIR` | `./data` | Data directory |
| `UPLOAD_DIR` | `./data/uploads` | Receipt/export file storage |
| `SESSION_COOKIE_NAME` | `exp_session` | Session cookie name |
| `SESSION_TTL_HOURS` | `8` | Session lifetime in hours |
| `MAX_UPLOAD_BYTES` | `5242880` | Max upload size (5 MB) |
| `ALLOWED_UPLOAD_MIME_TYPES` | `image/png,image/jpeg,application/pdf,text/plain` | Accepted receipt types |
| `WS_PATH` | `/ws` | WebSocket endpoint path |

## Database reset and seed

Reset the database and load deterministic seed fixtures (departments, cost centers, categories, users, reports, lines, receipts, approvals, finance reviews, comments, policy rules, export batches, audit trail):

```bash
npm run db:init        # reset + seed in one step
npm run db:reset       # recreate schema and tables (empty)
npm run db:seed        # seed existing database
```

## Startup

```bash
npm start
```

Then open <http://localhost:3000>. You are redirected to `/login.html` when signed out.

Development watch mode:

```bash
npm run dev
```

## Seed accounts

| Role | Username | Password |
| --- | --- | --- |
| Employee | `alice` | `employee123` |
| Employee | `bob` | `employee123` |
| Employee | `carol` | `employee123` |
| Manager | `mgr1` | `manager123` |
| Manager | `mgr2` | `manager123` |
| Finance | `finance1` | `finance123` |
| Admin | `admin` | `admin123` |

Seed data includes 8 expense reports (`EXP-2026-0001` … `EXP-2026-0008`) across the states draft, submitted, approved, changes_requested, finance_approved, and reimbursed so every actor has a working queue on first sign-in.

## Docker

```bash
docker compose up --build
```

Open <http://localhost:3000>. The compose file seeds the database on container start and keeps data in a `p08-data` volume.

Alternatively, build and run manually:

```bash
docker build -t p08-expense .
docker run --rm -p 3000:3000 -v p08-data:/app/data p08-expense
```

## Smoke check

A deterministic offline smoke test exercises the main workflows against a fresh database:

```bash
npm run smoke
```

It verifies login, report creation, submission, manager approval, finance review, CSV export, comments, policy listing, admin configuration, cross-user access rejection, and invalid-credential rejection.

## Application layout

```
scripts/            db-reset, db-seed, smoke-check
src/config.js       configuration + .env loader
src/server.js       entry point (HTTP + WebSocket)
src/app.js          Express app wiring and error handling
src/db/             schema.sql, database access, seed fixtures
src/lib/            errors, crypto, helpers
src/middleware/     session/role guards, error responses
src/services/       domain services (reports, approvals, policy, files, admin, ...)
src/routes/         REST controllers for every use case + auth + files + meta
src/ws/             WebSocket broadcast channel
src/public/         login page, SPA shell, CSS, vanilla JS client
```

## API overview

All business routes require an authenticated session cookie.

| Module | Route prefix | Methods |
| --- | --- | --- |
| Account access | `/api/exp/account_access` | GET, POST, PATCH `/:id` |
| Expense report creation | `/api/exp/expense_report_creation` | GET, POST, PATCH `/:id` |
| Receipt upload | `/api/exp/receipt_upload` | GET, POST, PATCH `/:id` |
| Report submission | `/api/exp/report_submission` | GET, POST, PATCH `/:id` |
| Manager approval | `/api/exp/manager_approval` | GET, POST, PATCH `/:id` |
| Finance review | `/api/exp/finance_review` | GET, POST, PATCH `/:id` |
| Policy rules | `/api/exp/policy_rules` | GET, POST, PATCH `/:id` |
| Comments and activity | `/api/exp/comments_and_activity` | GET, POST, PATCH `/:id` |
| Employee data access | `/api/exp/employee_data_access` | GET, POST, PATCH `/:id` |
| Reimbursement export | `/api/exp/reimbursement_export` | GET, POST, PATCH `/:id` |
| Admin configuration | `/api/exp/admin_configuration` | GET, POST, PATCH `/:id` |
| Frontend API integration and errors | `/api/exp/frontend_api_integration_and_errors` | GET, POST, PATCH `/:id` |
| Auth | `/api/auth/login`, `/api/auth/register`, `/api/auth/logout`, `/api/auth/me`, `/api/auth/dashboard` | POST/GET |
| Files | `/api/files/:id/download` | GET |
| Meta | `/api/meta/lookups`, `/api/meta/audit`, `/api/health` | GET |
| Real-time | `/ws` | WebSocket |

Errors are returned in a stable envelope `{ ok: false, error: { code, message, details? } }` with appropriate HTTP status codes and never leak internal stack traces.

## Use case traceability

| Use case | Title | Main implementation |
| --- | --- | --- |
| EXP-01 | Account access | `src/routes/accountAccessRoutes.js`, `src/routes/authRoutes.js`, `src/services/authService.js`, `src/middleware/auth.js`, `src/public/js/app.js` (account view, dashboard) |
| EXP-02 | Expense report creation | `src/routes/expenseReportRoutes.js`, `src/services/reportService.js`, `src/public/js/app.js` (reports view) |
| EXP-03 | Receipt upload | `src/routes/receiptRoutes.js`, `src/services/fileService.js`, `src/public/js/app.js` (detail view upload form) |
| EXP-04 | Report submission | `src/routes/submissionRoutes.js`, `src/services/reportService.js` (`submitReport`/`recallReport`) |
| EXP-05 | Manager approval | `src/routes/approvalRoutes.js`, `src/services/approvalService.js` (`managerDecision`, `managerApprovalQueue`), `src/public/js/app.js` (approvals view) |
| EXP-06 | Finance review | `src/routes/financeRoutes.js`, `src/services/approvalService.js` (`financeDecision`, `financeReviewQueue`) |
| EXP-07 | Policy rules | `src/routes/policyRoutes.js`, `src/services/policyService.js` |
| EXP-08 | Comments and activity | `src/routes/commentRoutes.js`, `src/services/approvalService.js` (`addComment`, `listComments`, `listActivity`), `src/ws/realtime.js` |
| EXP-09 | Employee data access | `src/routes/dataAccessRoutes.js`, `src/services/approvalService.js` (`listVisibleEmployees`, `recordDataAccess`) |
| EXP-10 | Reimbursement export | `src/routes/exportRoutes.js`, `src/services/fileService.js` (`buildCsvExport`, `listExportBatches`) |
| EXP-11 | Admin configuration | `src/routes/adminRoutes.js`, `src/services/adminService.js`, `src/routes/metaRoutes.js` (audit trail) |
| EXP-12 | Frontend API integration and errors | `src/routes/frontendRoutes.js`, `src/services/frontendService.js`, `src/public/js/api.js` (error handling), `src/middleware/auth.js`, error middleware in `src/app.js` |

## Deterministic choices

- Passwords are hashed with Node's built-in `scrypt` (salted); seed account passwords are listed above.
- File uploads use `multer` memory storage with deterministic MIME/size validation and are persisted under `UPLOAD_DIR`.
- Receipt/export "files" for seeds are deterministic local text fixtures; real uploads work identically via the same storage path.
- CSV export uses a fixed column set (`report_no, employee, title, total_amount, status, submitted_at`).
- Report numbers follow `EXP-<year>-<NNNN>` computed from the highest existing number.
- WebSocket broadcast is best-effort; the UI falls back to polling/reload for comment and decision lists.
