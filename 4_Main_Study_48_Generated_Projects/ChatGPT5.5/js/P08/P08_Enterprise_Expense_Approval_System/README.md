# P08 Enterprise Expense Approval System

LedgerFlow is a complete offline-capable expense approval application built with Node.js 22, Express 5, SQLite, server-side sessions, and a vanilla JavaScript browser client. It implements all 12 specified use cases and 39 HTTP endpoints.

## Prerequisites and local run

- Node.js 22 LTS and npm
- Docker with Compose (optional)

```bash
cp .env.example .env
npm ci
npm run db:reset
npm start
```

Open <http://localhost:8080>. The reset command recreates deterministic departments, reporting lines, policies, reports in multiple states, receipts, approvals, notifications, and review records.

## Seed accounts

All accounts use `Password123!`.

| Role | Email |
| --- | --- |
| Employee | `employee@example.test` |
| Other employee | `other@example.test` |
| Manager | `manager@example.test` |
| Other manager | `other-manager@example.test` |
| Finance | `finance@example.test` |
| Administrator | `admin@example.test` |

`disabled@example.test` is a disabled fixture for rejected-login behavior. Development password-reset requests return the deterministic local reset token; production mode returns only an accepted confirmation.

## Functional tests and coverage

```bash
npm run test:functional
npm run test:coverage
```

Every use case has one dedicated functional test file under `tests/functional/`. Tests start the real Express server on an ephemeral TCP port and use a fresh seeded SQLite database. The coverage command includes `src/**`, excludes only `src/server.js`, and fails below 85% overall branch coverage.

Verified result: **12/12 passed, 86.38% branch coverage, 100% line coverage, and 99.01% function coverage**. See [COVERAGE_REPORT.md](COVERAGE_REPORT.md).

## Docker

```bash
docker compose up --build
```

The image pins Node.js 22 and persists SQLite in the `expense-data` volume. To recreate the seeded volume:

```bash
docker compose down -v
docker compose up --build
```

## Deterministic request formats

- Receipt upload is JSON: `filename`, `mime_type`, and `content_base64`. MIME type and decoded size are restricted.
- Monetary amounts are integer cents; direct dependencies do not use floating-point currency arithmetic.
- Mutable reports, items, policies, roles, and related administrative resources use optimistic `version` checks where stale writes can matter.
- Expense submission resolves the policy effective on each item date, enforces category maximums and receipt thresholds, and resolves the approver from the stored reporting line.
- Reimbursement CSV is generated locally from verified, unbatched reports; processing a batch atomically marks its reports reimbursed.

## Use-case traceability

| ID | Workflow | Main routes | Functional test |
| --- | --- | --- | --- |
| EXP-01 | Account access | login, logout, password-reset request/reset | `exp01-account-access.test.js` |
| EXP-02 | Report creation | report create/edit and item create/edit/delete | `exp02-report-creation.test.js` |
| EXP-03 | Receipt upload | receipt upload/content/delete | `exp03-receipt-upload.test.js` |
| EXP-04 | Submission | `POST /api/expense-reports/:id/submit` | `exp04-report-submission.test.js` |
| EXP-05 | Manager approval | manager queue, approve, reject, return | `exp05-manager-approval.test.js` |
| EXP-06 | Finance review | finance queue, verify, return | `exp06-finance-review.test.js` |
| EXP-07 | Policy rules | expense-policy list/create/update | `exp07-policy-rules.test.js` |
| EXP-08 | Comments/activity | report activity and comments | `exp08-comments-activity.test.js` |
| EXP-09 | Data access | scoped reports, report detail, employee reports | `exp09-data-access.test.js` |
| EXP-10 | Reimbursement | batch create, CSV export, processed action | `exp10-reimbursement-export.test.js` |
| EXP-11 | Admin configuration | departments, managers, roles, currencies | `exp11-admin-configuration.test.js` |
| EXP-12 | Dashboard/notifications | dashboard, notifications, mark-read | `exp12-dashboard-notifications.test.js` |

The main route and workflow implementation is in `src/app.js`; SQLite session authentication is in `src/auth.js`; schema, repository access, and deterministic fixtures are in `database/schema.sql`, `src/repository.js`, and `src/seed.js`.

## Workflow and authorization guarantees

- Employees may change only their own draft or returned reports, items, and receipts.
- Managers see and decide only reports belonging to employees in their stored reporting line.
- Finance sees enterprise reports but may verify or return only manager-approved reports.
- Invalid transitions, stale versions, missing receipts, unsupported currencies, absent policies, exceeded category limits, repeated side effects, and cross-scope reads return stable errors.
- Multi-row submission, decisions, report-total recalculation, reimbursement processing, password reset, and currency updates use SQLite transactions.
- Error responses use `{ "error": { "code", "message", "fields" } }` without stack traces, file paths, or database details.
