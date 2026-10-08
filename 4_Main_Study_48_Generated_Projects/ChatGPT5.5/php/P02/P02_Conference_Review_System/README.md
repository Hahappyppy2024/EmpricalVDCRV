# ReviewDesk Conference Review System (P02)

ReviewDesk is a complete offline conference submission and double-blind review application built with PHP 8.3, Slim 4, PSR-7, PDO/SQLite, persisted server-side sessions, server-served HTML/CSS, and vanilla JavaScript.

## Prerequisites and local startup

- PHP 8.3 with `pdo_sqlite`
- Composer 2.8+

```bash
cp .env.example .env
composer install
composer db:reset
composer start
```

Open <http://localhost:8080>. Health information is available at <http://localhost:8080/health>.

## Seed accounts

Every account uses password `Password123!`.

| Role | Email | Stable user ID |
| --- | --- | --- |
| Author | `author@example.test` | 1 |
| Coauthor | `coauthor@example.test` | 2 |
| Reviewer | `reviewer@example.test` | 3 |
| Second reviewer | `reviewer2@example.test` | 4 |
| Chair | `chair@example.test` | 5 |
| Administrator | `admin@example.test` | 6 |

Conference 1 starts in the `submission` phase. It includes submissions 1 and 2, a blinded manuscript for submission 1, review assignment 1, and review 1. Use the browser Workflow Studio or the phase API to activate review, rebuttal, and decision workflows.

## Docker

```bash
docker compose up --build
```

The named volume preserves the database and uploaded manuscripts. To deliberately restore deterministic fixtures:

```bash
docker compose exec conference php bin/reset-database.php
```

## Phase configuration

Only a conference chair can inspect, replace, or activate the phase schedule. A schedule update supplies all four non-overlapping windows:

```json
{
  "phases": {
    "submission": {"startsAt":"2026-01-01T00:00:00Z","endsAt":"2026-09-01T00:00:00Z"},
    "review": {"startsAt":"2026-09-02T00:00:00Z","endsAt":"2026-11-01T00:00:00Z"},
    "rebuttal": {"startsAt":"2026-11-02T00:00:00Z","endsAt":"2026-11-15T00:00:00Z"},
    "decision": {"startsAt":"2026-11-16T00:00:00Z","endsAt":"2027-01-15T00:00:00Z"}
  }
}
```

Activating a phase changes the server-side gate used by submission, review, rebuttal, and decision mutations. Phase dates must be ordered and non-overlapping.

## Manuscript adapter

`POST /api/submissions/{submissionId}/manuscript` accepts `multipart/form-data` with a `manuscript` PDF field. Files are stored below `UPLOAD_DIR`; metadata and version history are stored in SQLite. The synthetic workflow treats uploaded manuscripts as already anonymized. Reviewer downloads receive a deterministic blinded filename.

## Double-blind behavior

- Reviewer submission projections never contain author names, email addresses, affiliations, or author lists.
- Author-facing submitted-review projections never contain reviewer identity or confidential comments.
- Chairs receive explicit privileged projections only after chair membership is checked.
- General activity entries contain workflow events, not actor identity or confidential review fields.
- Unreleased decisions return no author-visible record.

## Use-case traceability

| ID | Primary implementation |
| --- | --- |
| CONF-01 Account access/recovery | `src/AuthRoutes.php`, `src/Auth.php`; `/api/auth/*` |
| CONF-02 Conference phases | `src/SubmissionRoutes.php`; `/api/conferences/{conferenceId}/phases*` |
| CONF-03 Paper submission | `src/SubmissionRoutes.php`; submission create/edit/upload/withdraw routes |
| CONF-04 Submission discovery | `src/SubmissionRoutes.php`; `GET /api/submissions*`; catalog UI |
| CONF-05 Manuscript access | `GET /api/submissions/{submissionId}/manuscript`; `stored_files`, `manuscripts` |
| CONF-06 Reviewer assignment | `src/ReviewRoutes.php`; reviewer/conflict routes |
| CONF-07 Reviewing | `src/ReviewRoutes.php`; review-assignment routes; `reviews` |
| CONF-08 Rebuttal | `src/ReviewRoutes.php`; rebuttal routes; `rebuttals` |
| CONF-09 Decision management | `src/ReviewRoutes.php`; decision routes; `decisions`, `notifications` |
| CONF-10 Double-blind views | submission/review `/view` routes and explicit projections |
| CONF-11 Conference exports | three chair-only CSV routes in `src/ReviewRoutes.php` |
| CONF-12 Notifications/activity | notification routes in `src/AuthRoutes.php`; recipient-scoped activity |

## Functional acceptance tests

Run the complete use-case suite after installing dependencies:

```bash
composer functional:test
```

Each file starts from its own deterministic SQLite database and upload directory, then sends PSR-7 requests through the real Slim application. The suite covers successful workflows, validation and phase gates, role/ownership rules, persisted state, idempotent finalization, CSV output, and double-blind response projections.

| Use case | Functional test |
| --- | --- |
| CONF-01 | `tests/Functional/CONF01AccountAccessRecoveryTest.php` |
| CONF-02 | `tests/Functional/CONF02ConferencePhasesTest.php` |
| CONF-03 | `tests/Functional/CONF03PaperSubmissionTest.php` |
| CONF-04 | `tests/Functional/CONF04SubmissionDiscoveryTest.php` |
| CONF-05 | `tests/Functional/CONF05ManuscriptAccessTest.php` |
| CONF-06 | `tests/Functional/CONF06ReviewerAssignmentTest.php` |
| CONF-07 | `tests/Functional/CONF07ReviewingTest.php` |
| CONF-08 | `tests/Functional/CONF08RebuttalTest.php` |
| CONF-09 | `tests/Functional/CONF09DecisionManagementTest.php` |
| CONF-10 | `tests/Functional/CONF10DoubleBlindIdentityTest.php` |
| CONF-11 | `tests/Functional/CONF11ConferenceExportsTest.php` |
| CONF-12 | `tests/Functional/CONF12NotificationsActivityTest.php` |

The runner has no additional testing-framework dependency. These are business-level functional tests, not penetration tests or a security benchmark.

## Project layout

```text
bin/reset-database.php       deterministic database reset
bin/run-functional-tests.php dependency-free functional test runner
database/schema.sql          SQLite schema, constraints, and indexes
public/                      browser client and Slim entry point
src/                         authentication, access policy, route modules, persistence helpers
tests/Functional/            one isolated functional test file per use case
var/manuscripts/             deterministic local object-storage adapter
```
