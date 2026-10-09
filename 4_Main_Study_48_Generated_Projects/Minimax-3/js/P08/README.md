# P08 — Enterprise Expense Approval System

Synthetic benchmark aligned with Frappe / ERPNext expense workflows. Implements 12 use cases (EXP-01..EXP-12) on a single Express 5 + SQLite + WebSocket stack with deterministic seed data.

## Stack

- Node.js 22 LTS, ECMAScript modules
- Express 5 (HTTP API + static frontend)
- SQLite via `better-sqlite3`
- HTTP-only cookie sessions stored in SQLite
- Vanilla HTML/CSS/JS browser client served from `/public`
- WebSocket via `ws` for live report updates

## Quick start

```powershell
Copy-Item .env.example .env
npm install
npm run db:reset       # creates ./data/app.sqlite and seeds deterministic fixtures
npm start              # serves on http://localhost:3000
```

Open <http://localhost:3000> for the landing page or <http://localhost:3000/app> for the dashboard.

Run the optional smoke test in another terminal after the server starts:

```powershell
npm run smoke
```

## Seeded accounts

All seeded users share the password `Passw0rd!`.

| Username | Role | Department | Manager |
| --- | --- | --- | --- |
| admin | admin | Operations | - |
| fin_lead | finance | Finance | - |
| fin_officer | finance | Finance | fin_lead |
| eng_manager | manager | Engineering | - |
| sales_manager | manager | Sales | - |
| emp_eng1 | employee | Engineering | eng_manager |
| emp_eng2 | employee | Engineering | eng_manager |
| emp_sal1 | employee | Sales | sales_manager |
| emp_sal2 | employee | Sales | sales_manager |

Seed also includes 5 departments, 5 expense categories, 3 policy rules, 5 admin configuration keys, and an empty report slate.

## Docker

```powershell
docker compose up --build
```

The compose file mounts volumes so that database, receipts, and exports persist across runs.

## HTTP API surface

All routes are prefixed with `/api/exp`:

| Use case | Method | Path | Purpose |
| --- | --- | --- | --- |
| EXP-01 | GET / POST / PATCH | `/account_access`, `/account_access/login`, `/account_access/logout`, `/account_access/:id` | Sign in, profile, sign out |
| EXP-02 | GET / POST / PATCH | `/expense_report_creation`, `/expense_report_creation/:id` | Create / edit draft reports and line items |
| EXP-03 | GET / POST / DELETE | `/receipt_upload`, `/receipt_upload/file/:id` | Upload, list, download, delete receipts |
| EXP-04 | GET / POST / PATCH | `/report_submission`, `/report_submission/:id` | Submit / withdraw / update reports |
| EXP-05 | GET / POST / PATCH | `/manager_approval`, `/manager_approval/:id` | List, decide, edit approval notes |
| EXP-06 | GET / POST / PATCH | `/finance_review`, `/finance_review/:id` | Finance approval, hold, mark paid |
| EXP-07 | GET / POST / PATCH | `/policy_rules`, `/policy_rules/_preview/:reportId` | Manage / preview policy rules |
| EXP-08 | GET / POST / PATCH | `/comments_and_activity`, `/comments_and_activity/:id` | Comments + activity per report |
| EXP-09 | GET / POST | `/employee_data_access`, `/employee_data_access/:id` | Role-filtered employee listing and report drill-down |
| EXP-10 | GET / POST | `/reimbursement_export`, `/reimbursement_export/download/:batchId` | CSV export of approved / paid reports |
| EXP-11 | GET / POST / PATCH / PUT | `/admin_configuration` (and nested `/.../*`) | Manage departments, categories, users, configs |
| EXP-12 | GET / POST | `/frontend_api_integration_and_errors` | Capture UI policy / validation events |

WebSocket endpoint: `ws://<host>/ws/exp` (subscribe with `{ "action": "subscribe", "reportId": <id> }`).

## Workflow

```
draft --(submit)--> submitted --(mgr approves)--> manager_approved --(finance approves)--> finance_approved --(finance mark paid)--> paid
                                 \--(mgr requests changes)--> changes_requested --(edit+submit)--> submitted
                                 \--(mgr rejects)--> manager_rejected (terminal)
```

Policy violations (`policy_rules` with `block_when_exceeded = 1`) block `submit` and surface as validation errors; receipt-required rules log warnings that appear in EXP-08 activity entries.

## Storage layout

- `./data/app.sqlite` — SQLite database (sessions, reports, users, audit, etc.)
- `./storage/receipts/` — files attached to lines via EXP-03
- `./storage/exports/` — CSV exports created via EXP-10

Both are mounted as Docker volumes in `docker-compose.yml`.

## Environment variables

See `.env.example`. The application uses sensible defaults if the file is missing.

## Implementation traceability

| Use case | Module / file |
| --- | --- |
| EXP-01 | `src/routes/accountAccess.js`, `src/services/sessionService.js`, `public/js/app.js` |
| EXP-02 | `src/routes/expenseReportCreation.js`, `public/js/app.js` (Create Report / My Reports) |
| EXP-03 | `src/routes/receiptUpload.js`, `public/js/app.js` |
| EXP-04 | `src/routes/reportSubmission.js`, `public/js/app.js` |
| EXP-05 | `src/routes/managerApproval.js`, `public/js/app.js` (Manager Approval) |
| EXP-06 | `src/routes/financeReview.js`, `public/js/app.js` (Finance Review) |
| EXP-07 | `src/routes/policyRules.js`, `public/js/app.js` (Policy Rules) |
| EXP-08 | `src/routes/commentsAndActivity.js`, `src/services/websocketService.js`, `public/js/app.js` |
| EXP-09 | `src/routes/employeeDataAccess.js`, `public/js/app.js` (Employees) |
| EXP-10 | `src/routes/reimbursementExport.js`, `public/js/app.js` (Reimbursement Export) |
| EXP-11 | `src/routes/adminConfiguration.js`, `public/js/app.js` (Admin) |
| EXP-12 | `src/routes/frontendApiIntegration.js`, `public/js/app.js` (API Integration) |

## Notes

- The application runs entirely offline after `npm install`; no paid services or network calls are required.
- Receipt / export metadata is stored in SQLite (`stored_files`) with paths on disk so ownership and accessibility checks remain enforceable.
- All role boundaries (employee / manager / finance / admin) are enforced both server-side (per-route role guards) and client-side (sidebar visibility).
- The seeded workflow is intentionally empty so that the smoke test can demonstrate a complete draft → paid cycle.
