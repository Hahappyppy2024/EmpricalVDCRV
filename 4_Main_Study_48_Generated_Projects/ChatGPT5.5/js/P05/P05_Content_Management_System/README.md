# P05 Content Management System

Northstar CMS is a complete offline-capable publishing application built with Node.js 22, Express 5, SQLite, server-side sessions, and a vanilla JavaScript browser client. It implements all 12 specified use cases and 45 HTTP endpoints.

## Prerequisites

- Node.js 22 LTS and npm
- Docker with Compose (optional)

## Local setup

```bash
cp .env.example .env
npm ci
npm run db:reset
npm start
```

Open <http://localhost:8080>. `npm run db:reset` recreates the deterministic database. Node loads `.env` automatically when present; documented defaults are used when it is absent.

## Seed accounts

Every account uses password `Password123!`.

| Role | Email |
| --- | --- |
| Author | `author@example.test` |
| Other author | `other@example.test` |
| Editor | `editor@example.test` |
| Administrator | `admin@example.test` |
| Moderator | `moderator@example.test` |

`disabled@example.test` is a disabled author fixture used to verify rejected authentication. In development, the deterministic local password-reset adapter returns the reset token in the JSON response. Production mode returns only an accepted confirmation.

## Functional tests and branch coverage

```bash
npm run test:functional
npm run test:coverage
```

The functional suite starts the real Express application on an ephemeral TCP port and gives every use-case file a fresh seeded SQLite database. The coverage command includes application code under `src/`, excludes only the process-listening bootstrap `src/server.js`, and fails if overall branch coverage is below 85%.

Verified result: **12/12 tests passed, 88.50% branch coverage, 100% line coverage, and 100% function coverage**. See [COVERAGE_REPORT.md](COVERAGE_REPORT.md).

## Docker

```bash
docker compose up --build
```

The image uses Node.js 22 and persists SQLite in the `cms-data` volume. For a completely fresh seeded volume:

```bash
docker compose down -v
docker compose up --build
```

## API representation choices

- Media upload uses JSON with `filename`, `mime_type`, `content_base64`, optional `alt_text`, and optional `caption`. MIME types are allow-listed and decoded content is size-bounded by `MAX_MEDIA_BYTES`.
- Rich text is a JSON array of `{ "type": "heading|paragraph|quote", "text": "..." }` blocks. Preview output HTML-escapes user text.
- Imported packages use `{ "items": [...], "idempotency_key": "..." }`; the job and all content rows are committed atomically.
- Integration records store passive allow-listed configuration only. They cannot install or execute plugins.
- Navigation and redirects accept site-local paths only.

## Use-case traceability

| ID | Workflow | Routes | Functional test |
| --- | --- | --- | --- |
| CMS-01 | Account access | `/api/auth/register`, `/login`, `/logout`, `/password-reset-requests`, `/password-resets` | `cms01-account-access.test.js` |
| CMS-02 | Content authoring | `POST/GET/PATCH/DELETE /api/content` | `cms02-content-authoring.test.js` |
| CMS-03 | Rich-text editing | `PUT /api/content/:id/body`, `POST /preview` | `cms03-rich-text.test.js` |
| CMS-04 | Media library | `GET/POST /api/media`, metadata/content/delete routes | `cms04-media-library.test.js` |
| CMS-05 | Publishing workflow | submit, approve, reject, schedule, unpublish actions | `cms05-publishing-workflow.test.js` |
| CMS-06 | Public site | `GET /api/public/content`, `GET /:slug` | `cms06-public-site.test.js` |
| CMS-07 | Comments | public comment list/create and admin moderation | `cms07-comments.test.js` |
| CMS-08 | Page templates | template list/create/edit and content assignment | `cms08-page-templates.test.js` |
| CMS-09 | User/role management | admin users and roles routes | `cms09-user-role-management.test.js` |
| CMS-10 | Site settings | admin settings and integrations routes | `cms10-site-settings.test.js` |
| CMS-11 | Import/export | `POST /api/content-imports`, `GET /api/content-exports` | `cms11-import-export.test.js` |
| CMS-12 | Navigation/redirects | navigation and redirect management routes | `cms12-navigation-redirects.test.js` |

The main route implementation is `src/app.js`; authentication and SQLite sessions are in `src/auth.js`; persistence is defined by `database/schema.sql`, `src/database.js`, and `src/repository.js`; deterministic fixtures are in `src/seed.js`.

## Behavioral guarantees

- Authors cannot read or mutate another author's private content or media; editors can manage editorial resources; administrative and moderation operations require their designated roles.
- Content workflow changes reject invalid states, and mutable resources use optimistic version checks where stale writes matter.
- Multi-row rich-text revision, publishing transition, settings, password reset, and import operations use SQLite transactions where appropriate.
- Public endpoints return published content and published comments only.
- Common error responses use `{ "error": { "code", "message", "fields" } }` and do not expose stack traces or database details.
