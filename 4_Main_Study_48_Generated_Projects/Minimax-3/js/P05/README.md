# Synthetic CMS (P05 — Content Management System)

A benchmark content management system built with **Node.js 22 LTS**, **Express 5**, **SQLite (better-sqlite3)**, server-side sessions, and a vanilla-JS browser client. Implements all 12 use cases (`CMS-01` … `CMS-12`) end-to-end.

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | Node.js 22 LTS |
| Web framework | Express 5 |
| Database | SQLite via `better-sqlite3` |
| Auth | Server-side sessions (HTTP-only cookie) stored in SQLite |
| Frontend | Server-served HTML/CSS/Vanilla JS |
| Real-time | `ws` (WebSocket) at `/ws` for live notifications |
| Packaging | npm with pinned dependencies and `package-lock.json` |
| Container | Dockerfile + docker-compose |

## Prerequisites

- Node.js 22 LTS (`node -v` should report v22.x)
- npm 10+
- Docker 20+ (optional, for container run)

## Setup

```bash
npm install
cp .env.example .env   # adjust as needed
npm run db:reset       # create schema and load deterministic seed
npm start
```

The server starts at http://localhost:3000.

## Seeded accounts

| Username | Password | Role |
| --- | --- | --- |
| admin | admin123 | admin |
| editor1 | editor123 | editor |
| author1 | author123 | author |
| author2 | author123 | author |
| mod1 | moderator123 | moderator |
| visitor1 | visitor123 | visitor |

Additional accounts can be created via `/register` (role: visitor) or via the admin user panel.

## npm scripts

- `npm start` — run the application
- `npm run dev` — run with `--watch`
- `npm run db:reset` — drop & re-create schema, load deterministic seed
- `npm run db:seed` — re-seed the existing schema

## Docker

```bash
docker compose up --build
```

The compose file mounts `./data` and `./uploads` to keep the database and uploaded media across runs.

## Project layout

```
src/
  app.js               Express app builder + page routes
  server.js            HTTP + WebSocket bootstrap, seed orchestration
  renderer.js          Tiny template renderer used by views
  db/
    database.js        better-sqlite3 connection + schema loader
    schema.sql         Full SQLite schema for all 12 use cases
    seed.js            Deterministic seed fixtures
    reset.js           Drop & re-seed script
  middleware/
    auth.js            Session attach + role guards
    error.js           Error & 404 handlers
  routes/              One router per use case (CMS-01..CMS-12)
  services/
    session.js         Session creation, lookup, deletion
    password.js        Deterministic password hashing
    errors.js          Error helpers
    audit.js           Audit log writers
    realtime.js        WebSocket broadcast hub
  views/               HTML templates
public/
  css/                 Static stylesheet
  js/                  Static scripts
uploads/               Local file storage for media
data/                  SQLite database file (auto-created)
```

## API surface

All JSON APIs are mounted under `/api/cms`.

| Use case | Method + Path | Description |
| --- | --- | --- |
| CMS-01 | `GET /api/cms/account_access` | Current session info |
| CMS-01 | `POST /api/cms/account_access?action=login` | Sign in |
| CMS-01 | `POST /api/cms/account_access?action=register` | Register visitor |
| CMS-01 | `PATCH /api/cms/account_access/:id` | Update profile |
| CMS-01 | `POST /api/cms/account_access/logout` | Sign out |
| CMS-02 | `GET /api/cms/content_authoring` | List articles |
| CMS-02 | `POST /api/cms/content_authoring` | Create article |
| CMS-02 | `GET /api/cms/content_authoring/:id` | Get article |
| CMS-02 | `PATCH /api/cms/content_authoring/:id` | Update article |
| CMS-02 | `DELETE /api/cms/content_authoring/:id` | Delete article |
| CMS-03 | `GET /api/cms/rich_text_editor` | List revisions |
| CMS-03 | `POST /api/cms/rich_text_editor` | Save rich text revision |
| CMS-03 | `PATCH /api/cms/rich_text_editor/:id` | Update revision |
| CMS-03 | `GET /api/cms/rich_text_editor/preview/:token` | Render preview HTML |
| CMS-04 | `GET /api/cms/media_library` | List media |
| CMS-04 | `POST /api/cms/media_library` | Upload (multipart `file`) |
| CMS-04 | `PATCH /api/cms/media_library/:id` | Rename / set private |
| CMS-04 | `DELETE /api/cms/media_library/:id` | Delete media |
| CMS-05 | `GET /api/cms/publishing_workflow` | List workflow entries |
| CMS-05 | `POST /api/cms/publishing_workflow` | Transition state |
| CMS-05 | `PATCH /api/cms/publishing_workflow/:id` | Update notes/schedule |
| CMS-06 | `GET /api/cms/public_site` | Public listing + categories + menus |
| CMS-06 | `GET /api/cms/public_site/page/:slug` | Public single page |
| CMS-06 | `POST /api/cms/public_site` | Public search |
| CMS-07 | `GET /api/cms/comments` | List comments |
| CMS-07 | `POST /api/cms/comments` | Submit comment |
| CMS-07 | `PATCH /api/cms/comments/:id` | Moderate |
| CMS-08 | `GET /api/cms/page_templates` | List templates & menus |
| CMS-08 | `POST /api/cms/page_templates` | Create template |
| CMS-08 | `PATCH /api/cms/page_templates/:id` | Update template |
| CMS-08 | `POST /api/cms/page_templates/menus` | Add menu item |
| CMS-08 | `PATCH /api/cms/page_templates/menus/:id` | Update menu item |
| CMS-08 | `DELETE /api/cms/page_templates/menus/:id` | Delete menu item |
| CMS-09 | `GET /api/cms/user_and_role_management` | List users, roles, history |
| CMS-09 | `POST /api/cms/user_and_role_management` | Create user / assign role |
| CMS-09 | `PATCH /api/cms/user_and_role_management/:id` | Update user |
| CMS-10 | `GET /api/cms/plugin_settings_panel` | List settings |
| CMS-10 | `POST /api/cms/plugin_settings_panel` | Add setting |
| CMS-10 | `PATCH /api/cms/plugin_settings_panel/:id` | Update setting |
| CMS-11 | `GET /api/cms/import_export` | List jobs |
| CMS-11 | `POST /api/cms/import_export` | Run export or import |
| CMS-11 | `GET /api/cms/import_export/download/:id` | Download export file |
| CMS-12 | `GET /api/cms/frontend_api_integration_and_errors` | List integration events |
| CMS-12 | `POST /api/cms/frontend_api_integration_and_errors` | Log / simulate responses |

## Real-time

Connect to `ws://localhost:3000/ws`. The server pushes `comment.created`, `comment.updated`, and a `hello` message on connect. Ping with `{ "type": "ping" }` to receive `{ "type": "pong", ts }`.

## Use-case traceability

| Use case | Primary implementation |
| --- | --- |
| CMS-01 Account access | `src/routes/account.js`, `src/services/session.js`, `src/middleware/auth.js`, `src/views/login.html`, `src/views/register.html` |
| CMS-02 Content authoring | `src/routes/articles.js`, `src/views/articles.html`, `src/views/article_form.html` |
| CMS-03 Rich text editor | `src/routes/richtext.js`, `src/views/article_form.html` |
| CMS-04 Media library | `src/routes/media.js`, `src/views/media.html`, `uploads/` |
| CMS-05 Publishing workflow | `src/routes/publishing.js`, `src/views/workflow.html` |
| CMS-06 Public site | `src/routes/public.js`, `src/views/public_articles.html`, `src/views/article.html`, `src/views/home.html` |
| CMS-07 Comments | `src/routes/comments.js`, `src/services/realtime.js`, `src/views/comments.html` |
| CMS-08 Page templates | `src/routes/templates.js`, `src/views/templates.html` |
| CMS-09 User and role management | `src/routes/users.js`, `src/services/audit.js`, `src/views/users.html` |
| CMS-10 Plugin/settings panel | `src/routes/settings.js`, `src/views/settings.html` |
| CMS-11 Import/export | `src/routes/importexport.js`, `src/views/importexport.html` |
| CMS-12 Frontend API integration and errors | `src/routes/frontend.js`, `src/services/audit.js`, `src/views/frontend.html` |

## Browser pages

| Page | Path | Roles |
| --- | --- | --- |
| Home | `/` | All |
| Login | `/login` | Anonymous |
| Register | `/register` | Anonymous |
| Dashboard | `/dashboard` | Any signed-in user |
| Articles list | `/articles` | Any signed-in user |
| New / edit article | `/articles/new`, `/articles/:id` | author / editor / admin |
| Media library | `/media` | author / editor / admin |
| Publishing workflow | `/workflow` | editor / admin |
| Comments moderation | `/comments` | moderator / editor / admin |
| Templates | `/templates` | editor / admin |
| Users & roles | `/users` | admin |
| Settings | `/settings` | admin |
| Import / Export | `/importexport` | admin |
| Frontend API tools | `/frontend` | Any signed-in user |
| Public articles | `/articles-list` | Anonymous |
| Public article | `/articles/:slug` | Anonymous |

## Environment variables

See `.env.example`:

- `PORT` — HTTP port (default 3000)
- `NODE_ENV` — `development` / `production`
- `SESSION_SECRET` — secret string (currently informational)
- `DB_PATH` — SQLite file path
- `UPLOAD_DIR` — local upload directory
- `MAX_UPLOAD_BYTES` — upload size limit

## Notes

- The application runs entirely offline after `npm install`.
- All external services (email, payments, storage) are deterministic and local.
- The implementation is independent of the real-world alignment target; no source code is reused.
