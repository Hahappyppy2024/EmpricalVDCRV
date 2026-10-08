# P05 — Content Management System

A complete, runnable content management web application generated from the synthetic benchmark specification (Version A, JavaScript technology profile).

The application implements all twelve use cases (CMS-01 … CMS-12) as one integrated system:

- **Account access** — sign-up, sign-in, sign-out with server-side sessions stored in SQLite.
- **Content authoring** — authors create and edit articles (drafts) with title, body, tags, category and status.
- **Rich text editor** — toolbar formatting (bold, italic, headings, lists, quotes, links) with live preview and version history.
- **Media library** — upload, list, rename, set visibility, delete and insert files; files are stored locally and linked to their owner.
- **Publishing workflow** — articles move through `draft → review → scheduled → published` with validated transitions, an editor queue, auto-publish of scheduled content and WebSocket live updates.
- **Public site** — visitors browse published articles, categories, search results and post comments.
- **Comments** — visitor comments are submitted, then approved/removed by moderators.
- **Page templates** — editors/admins manage page templates and navigation menus.
- **User and role management** — admins create users, assign roles, enable/disable accounts; privileged actions are audited.
- **Plugin/settings panel** — admins configure site settings, widgets and integrations.
- **Import/export** — admins export the site as JSON or CSV and import JSON content.
- **Frontend API integration and errors** — diagnostics panel demonstrates deterministic validation, missing-page, permission and server errors; UI handles them gracefully.

## Technology stack

| Layer | Choice |
| --- | --- |
| Language | JavaScript (ECMAScript modules) |
| Runtime | Node.js 22 LTS |
| Web framework | Express 5 (`express@5.1.0`) |
| Database | SQLite via `better-sqlite3@11.9.1` (repository/data-access layer) |
| Authentication | Server-side sessions, HTTP-only cookie, session rows in SQLite |
| Browser client | Server-served HTML + CSS + vanilla JavaScript (no build step) |
| Real-time transport | `ws@8.18.0` WebSocket server at `/ws` |
| File uploads | `multer@2.0.1` (local deterministic storage) |
| Config | `dotenv@16.4.7`, documented in `.env.example` |

All dependency versions are pinned exactly in `package.json` and locked in `package-lock.json`.

## Project layout

```
P05/
├── package.json / package-lock.json
├── .env.example
├── Dockerfile
├── docker-compose.yml
├── README.md
├── scripts/
│   ├── reset-db.js        # wipe + recreate + seed the SQLite database
│   └── seed.js            # seed an existing database (idempotent)
├── src/
│   ├── server.js          # entry point (HTTP + WebSocket)
│   ├── app.js             # express app + all route mounts
│   ├── config/env.js      # environment configuration
│   ├── lib/               # crypto, validation, sanitize, http helpers
│   ├── middleware/        # session auth, error handling, file upload
│   ├── db/
│   │   ├── index.js       # better-sqlite3 connection
│   │   ├── schema.js      # full SQL schema
│   │   ├── repo.js        # generic repository helper
│   │   ├── repositories.js# per-entity repositories
│   │   └── seed.js        # deterministic seed fixtures
│   ├── services/          # realtime (ws), audit, publishing, import/export
│   ├── controllers/       # one controller per use case + auth + pages
│   ├── public/            # CSS + vanilla JS browser client
│   └── views/             # server-served HTML pages
└── data/                  # created at runtime: cms.db, uploads/, exports/
```

## Prerequisites

- Node.js **22 LTS** (tested with v22.21.0) and npm 10+.
- No external services, accounts or network access are required after dependencies are installed. Email, payments, object storage, model responses and repository integrations are replaced by deterministic local adapters.

## Setup

### 1. Install dependencies

```bash
npm install
```

> Note: `better-sqlite3` ships prebuilt binaries for Node 22 on common platforms; if a build is required, ensure a C++ toolchain (Visual Studio Build Tools on Windows, `build-essential` on Linux) is available.

### 2. Configure environment

```bash
# Windows
copy .env.example .env

# macOS / Linux
cp .env.example .env
```

All values in `.env.example` are local-safe defaults. `DB_PATH`, `UPLOAD_DIR` and `EXPORT_DIR` are resolved relative to the project root.

### 3. Reset and seed the database

```bash
npm run setup          # == npm run reset-db && npm run seed
```

`reset-db` deletes the database and upload/export folders, recreates the schema, and inserts deterministic seed fixtures. `seed` alone seeds an existing (empty) database and is a no-op if data already exists.

The database is created at `data/cms.db` (WAL mode). Uploads go to `data/uploads/`, exports to `data/exports/`.

### 4. Start the application

```bash
npm start              # http://localhost:3000
```

Development watch mode: `npm run dev`.

### Docker

```bash
docker compose up --build
# container reseeds the database on first start and exposes port 3000
```

Build-only alternative:

```bash
docker build -t p05-cms .
docker run --rm -p 3000:3000 -v "$PWD/data:/app/data" p05-cms
```

## Seed accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | Administrator — full access |
| `editor` | `editor123` | Editor — review, schedule, publish, templates |
| `author` | `author123` | Author — author and submit own content |
| `moderator` | `moderator123` | Moderator — approve/remove comments |
| `visitor` | `visitor123` | Visitor — public site + comments |

Seed content: 6 articles across all four workflow states (`draft`, `review`, `scheduled`, `published`), 3 categories, 3 page templates, 5 navigation menus, 3 media assets, 6 comments (pending/approved/removed), settings, import/export history, and audit events.

## Public site

- `GET /` — latest published articles, category links, search
- `GET /article/:slug` — article detail, rich text rendering, comments
- `GET /category/:slug` and `GET /search?q=…` — filtered listings

Visitors do not need an account. Commenting is open; with `moderate_comments=true` (default), new comments start as `pending` and are visible only after a moderator approves them.

## Dashboard (signed in)

Sign in at `/login`. The dashboard is a single-page shell (`/dashboard`, plus routes such as `/content`, `/media`, `/publishing`, `/templates`, `/admin/users`, `/admin/settings`, `/admin/import-export`, `/diagnostics`) whose views are rendered by vanilla JavaScript from the API below.

## API summary

All API responses use the envelope `{ ok, data, message }` (plus `meta` on list endpoints) or `{ ok:false, error:{ code, message } }` for failures. No stack traces are exposed.

| Area | Endpoints |
| --- | --- |
| Auth | `POST /api/auth/register` · `POST /api/auth/login` · `POST /api/auth/logout` · `GET /api/auth/me` |
| CMS-01 Account access | `GET|POST /api/cms/account_access` · `PATCH /api/cms/account_access/:id` |
| CMS-02 Content authoring | `GET|POST /api/cms/content_authoring` · `GET|PATCH|DELETE /api/cms/content_authoring/:id` |
| CMS-03 Rich text editor | `GET|POST /api/cms/rich_text_editor` · `PATCH /api/cms/rich_text_editor/:id` |
| CMS-04 Media library | `GET|POST /api/cms/media_library` (multipart) · `PATCH|DELETE /api/cms/media_library/:id` · `GET /api/cms/media_library/:id/file` |
| CMS-05 Publishing workflow | `GET|POST /api/cms/publishing_workflow` · `GET /api/cms/publishing_workflow/queue` · `PATCH /api/cms/publishing_workflow/:id` |
| CMS-06 Public site | `GET|POST /api/cms/public_site` · `PATCH /api/cms/public_site/:id` · `GET /api/public/articles`, `/api/public/articles/:slug`, `/api/public/categories`, `/api/public/search`, `/api/public/settings` |
| CMS-07 Comments | `GET|POST /api/cms/comments` · `PATCH|DELETE /api/cms/comments/:id` |
| CMS-08 Page templates | `GET|POST /api/cms/page_templates` · `PATCH|DELETE /api/cms/page_templates/:id` · `POST|PATCH|DELETE /api/cms/page_templates/menus(/:id)` |
| CMS-09 User & role mgmt | `GET|POST /api/cms/user_and_role_management` · `GET .../roles` · `PATCH .../:id` · `GET /api/audit` |
| CMS-10 Plugin/settings | `GET|POST /api/cms/plugin_settings_panel` · `PATCH|DELETE /api/cms/plugin_settings_panel/:id` |
| CMS-11 Import/export | `GET|POST /api/cms/import_export` (multipart import / JSON export) · `PATCH /api/cms/import_export/:id` · `GET /api/cms/import_export/:id/download` |
| CMS-12 Frontend API & errors | `GET|POST /api/cms/frontend_api_integration_and_errors` · `PATCH .../:id` · `GET /api/diagnostics/{validation,not-found,forbidden,server-error,preview}` |
| Health | `GET /api/health` |

## WebSocket events

Connect to `ws://localhost:3000/ws`. The dashboard subscribes and reacts live:

- `publishing:transition` / `content:status` — workflow transitions (published, scheduled, etc.)
- `comment:new` (pending), `comment:moderated`
- `content:created|updated|deleted`, `editor:updated`
- `media:uploaded|updated|deleted`
- `settings:updated`, `menu:updated`, `user:created|updated`, `import_export:done`, `auth:signed_out`

Clients may send `{ "type": "ping" }` and receive `{ "type": "pong" }`. Server heartbeats terminate stale connections.

## Publishing workflow rules

| From | To | Allowed actors |
| --- | --- | --- |
| `draft` | `review` | author (own), editor, admin |
| `review` | `scheduled` / `published` / `draft` | editor, admin |
| `scheduled` | `published` / `review` | editor, admin (auto-publish also fires) |
| `published` | `draft` | editor, admin |

Scheduling requires `publish_at`. A background ticker (60 s) plus on-read checks auto-publish scheduled articles whose date has passed. Invalid transitions return a deterministic `422`.

## Permissions

- **admin**: everything (incl. user/role management, settings, import/export, deleting templates/menus).
- **editor**: all content, publishing decisions, templates, comment moderation.
- **author**: create/edit/delete own draft & in-review articles, submit for review, upload own media, rich text editing, view own workflow history.
- **moderator**: approve/remove comments.
- **visitor / anonymous**: browse public site, post comments (await moderation).

Ownership is enforced: authors can only touch their own content; private media is only accessible to its owner and admins (`CMS-04-FA-03`, `CMS-11-FA-03`).

## Traceability table

| Use case | Main routes | Main implementation files |
| --- | --- | --- |
| CMS-01 Account access | `POST /api/auth/*`, `GET /api/auth/me`, `GET|POST /api/cms/account_access`, `PATCH /api/cms/account_access/:id` | `src/controllers/authController.js`, `src/controllers/accountAccessController.js`, `src/middleware/auth.js` |
| CMS-02 Content authoring | `GET|POST /api/cms/content_authoring`, `GET|PATCH|DELETE /api/cms/content_authoring/:id` | `src/controllers/contentAuthoringController.js`, `src/views/app.html` (`#/content`) |
| CMS-03 Rich text editor | `GET|POST /api/cms/rich_text_editor`, `PATCH /api/cms/rich_text_editor/:id` | `src/controllers/richTextEditorController.js`, `src/public/js/richtext.js` |
| CMS-04 Media library | `GET|POST /api/cms/media_library`, `PATCH|DELETE /api/cms/media_library/:id`, `GET .../:id/file` | `src/controllers/mediaLibraryController.js`, `src/middleware/upload.js`, `src/db/schema.js` (`stored_file`, `media_library`) |
| CMS-05 Publishing workflow | `GET|POST /api/cms/publishing_workflow`, `GET .../queue`, `PATCH .../:id` | `src/controllers/publishingWorkflowController.js`, `src/services/publishing.js`, `src/services/realTime.js` |
| CMS-06 Public site | `GET|POST /api/cms/public_site`, `PATCH .../:id`, `GET /api/public/*` | `src/controllers/publicSiteController.js`, `src/views/home.html`, `src/views/article.html`, `src/public/js/public.js` |
| CMS-07 Comments | `GET|POST /api/cms/comments`, `PATCH|DELETE /api/cms/comments/:id` | `src/controllers/commentsController.js`, `src/public/js/public.js` (comment form) |
| CMS-08 Page templates | `GET|POST /api/cms/page_templates`, `PATCH|DELETE .../:id`, `POST|PATCH|DELETE /api/cms/page_templates/menus(/:id)` | `src/controllers/pageTemplatesController.js` |
| CMS-09 User and role management | `GET|POST /api/cms/user_and_role_management`, `PATCH .../:id`, `GET /api/audit` | `src/controllers/userRoleController.js`, `src/services/audit.js` |
| CMS-10 Plugin/settings panel | `GET|POST /api/cms/plugin_settings_panel`, `PATCH|DELETE .../:id` | `src/controllers/pluginSettingsController.js` |
| CMS-11 Import/export | `GET|POST /api/cms/import_export`, `PATCH .../:id`, `GET .../:id/download` | `src/controllers/importExportController.js`, `src/services/importExport.js` |
| CMS-12 Frontend API integration & errors | `GET|POST /api/cms/frontend_api_integration_and_errors`, `PATCH .../:id`, `GET /api/diagnostics/*` | `src/controllers/frontendApiController.js`, `src/middleware/errors.js`, `src/public/js/app.js` (`#/diagnostics`) |

## Smoke-check result

A runtime verification run (fresh database) confirmed **51/51 checks pass**, covering: health, public pages and API, login/logout and session cookies, all twelve use-case route groups (list/create/update/delete as applicable), permission errors (401/403), controlled validation (400), missing resources (404), controlled server error (500, no stack trace), publishing transitions including an invalid-transition `422`, media upload/file streaming, JSON+CSV export, comment moderation, and WebSocket server mounting. The verification harness is not shipped as part of the application project.
