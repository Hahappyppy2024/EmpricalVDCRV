# P10 — AI Assistant / LLM WebUI

Synthetic benchmark web application aligned with the high-level workflow anchors of **Lobe Chat**
(conversations, model/provider configuration, prompt templates, plugins/tools, proxy/API routes,
API keys, sharing, and audit logs). This is an independent implementation; it does not copy any
code from the real-world project.

- Language / runtime: **JavaScript (ESM)** on **Node.js 22 LTS**
- Web framework: **Express 5**
- Database: **SQLite** via **better-sqlite3** through a repository/data-access layer
- Auth: server-side sessions in SQLite, delivered via an HTTP-only cookie
- Browser client: server-served HTML, CSS, and vanilla JavaScript
- Real-time: **ws** WebSocket channel (`/ws`) broadcasting chat execution events
- Everything runs offline after `npm install` (deterministic local adapters; no external accounts)

## 1. Prerequisites

- Node.js **>= 22** (LTS) and npm (>= 10)
- Docker + Docker Compose (optional, for containerized execution)

## 2. Install dependencies

```bash
npm install
```

This installs the pinned dependencies listed in `package-lock.json`. On npm 11+ the
`better-sqlite3` install script is gated; approve it when prompted:

```bash
npm install-scripts approve better-sqlite3
```

## 3. Environment configuration

Copy `.env.example` to `.env` and adjust as needed:

```bash
cp .env.example .env
```

Defaults (no `.env` required to run):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `3000` | HTTP listen port |
| `DATABASE_PATH` | `./data/app.db` | SQLite database file |
| `UPLOAD_DIR` | `./data/uploads` | Uploaded knowledge file storage |
| `MAX_UPLOAD_SIZE_BYTES` | `5242880` | Max upload size (5 MB) |
| `ALLOWED_UPLOAD_MIME_TYPES` | `text/plain,text/markdown,text/csv,application/json` | Allowed upload MIME types |
| `SESSION_TTL_MS` | `604800000` | Session lifetime (7 days) |
| `SESSION_COOKIE_NAME` | `aiwebui_sid` | HTTP-only session cookie name |
| `ALLOW_REGISTRATION` | `true` | Allow new account registration |
| `DEFAULT_PROVIDER` / `DEFAULT_MODEL` | `openai` / `gpt-4o-mini` | Fallback model profile |

## 4. Database reset and seed

The server seeds automatically on first start. To manage the database explicitly:

```bash
npm run db:reset   # delete DB + uploads, recreate schema, seed fixtures (deterministic)
npm run db:seed    # idempotent seed; skips if seed data already exists
```

The database file is `data/app.db`; uploaded files live in `data/uploads/`.

### Seed accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | admin |
| `alice` | `alice123` | user |
| `bob` | `bob123` | user |

Seed fixtures include conversations + messages, prompt templates, model configurations,
knowledge files (with stored uploads), retrieval collections, tool/plugin registry + per-user
enabled tools, masked API keys, chat executions, share links, audit events, usage logs, and
admin settings (blocked terms, model access, defaults).

## 5. Start

```bash
npm start
```

Open <http://localhost:3000>. The root path redirects to `/auth` (sign-in) or `/app` (dashboard)
based on session state.

Health check: `GET /api/health`.

### Smoke checks

With the server running on port 3000, run the built-in verification scripts (Node-only, no
external dependencies):

```bash
npm run smoke      # API smoke check across all 12 use cases
npm run smoke:ws   # pages, static assets, and WebSocket realtime broadcast
```

## 6. Docker

```bash
docker compose up --build
```

or build/run manually:

```bash
docker build -t p10-ai-assistant .
docker run --rm -p 3000:3000 -v "$(pwd)/data:/app/data" p10-ai-assistant
```

The container seeds the database on first start and serves on <http://localhost:3000>.

## 7. Key API surface

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/api/auth/register` | Register (role `user`); sets session cookie |
| POST | `/api/auth/login` | Sign in; sets session cookie |
| POST | `/api/auth/logout` | Sign out; destroys session |
| GET | `/api/auth/me` | Current session user |
| GET | `/api/health` | Health check |
| WS | `/ws` | Realtime channel (authenticated via session cookie or `?token=`) |
| GET | `/api/share/:token` | Public read-only JSON of a shared conversation |
| GET | `/share/:token` | Public read-only share page (HTML) |

All `/api/ai/*` routes require authentication; admin-only routes return `403`.

## 8. Traceability table (use cases → implementation)

| Use case | Title | Routes | Main files |
| --- | --- | --- | --- |
| AI-01 | Account access | `GET/POST /api/ai/account_access`, `PATCH /api/ai/account_access/:id`, `/api/auth/*` | `src/routes/auth.js`, `src/routes/modules.js`, `src/middleware/auth.js`, `public/js/app.js` (Account view), `public/js/auth.js` |
| AI-02 | Conversation management | `GET/POST /api/ai/conversation_management`, `PATCH /api/ai/conversation_management/:id`, `GET .../:id/messages` | `src/routes/modules.js`, `src/db/schema.sql`, `public/js/app.js` (Chat view) |
| AI-03 | Prompt templates | `GET/POST /api/ai/prompt_templates`, `PATCH /api/ai/prompt_templates/:id` | `src/routes/modules.js`, `public/js/app.js` (Templates view) |
| AI-04 | Model configuration | `GET/POST /api/ai/model_configuration`, `PATCH /api/ai/model_configuration/:id` | `src/routes/modules.js`, `src/services/settingsService.js` (model access), `public/js/app.js` |
| AI-05 | Knowledge file upload | `GET/POST /api/ai/knowledge_file_upload`, `PATCH /api/ai/knowledge_file_upload/:id` | `src/services/fileService.js`, `src/routes/modules.js`, `public/js/app.js` |
| AI-06 | Retrieval collection | `GET/POST /api/ai/retrieval_collection`, `PATCH /api/ai/retrieval_collection/:id`, `GET .../:id` | `src/services/chatService.js` (searchKnowledge), `src/routes/modules.js`, `public/js/app.js` |
| AI-07 | Tool/plugin registry | `GET/POST /api/ai/tool_plugin_registry`, `PATCH /api/ai/tool_plugin_registry/:id` | `src/routes/modules.js`, `src/db/schema.sql` (tool_plugins, user_tools) |
| AI-08 | API key management | `GET/POST /api/ai/api_key_management`, `PATCH /api/ai/api_key_management/:id` | `src/routes/modules.js`, `public/js/app.js` (Keys view) |
| AI-09 | Chat execution | `GET/POST /api/ai/chat_execution`, `PATCH /api/ai/chat_execution/:id`, `WS /ws` | `src/services/chatService.js`, `src/services/modelAdapter.js`, `src/realtime/ws.js`, `public/js/app.js` (Chat view) |
| AI-10 | Share conversation | `GET/POST /api/ai/share_conversation`, `PATCH /api/ai/share_conversation/:id`, `GET /api/share/:token`, `GET /share/:token` | `src/routes/share.js`, `src/routes/modules.js`, `public/share.html`, `public/js/share.js` |
| AI-11 | Usage and audit logs | `GET/POST /api/ai/usage_and_audit_logs`, `PATCH /api/ai/usage_and_audit_logs/:id` | `src/services/usageService.js`, `src/services/auditService.js`, `src/routes/modules.js`, `public/js/app.js` (Logs view) |
| AI-12 | Admin moderation and settings | `GET/POST /api/ai/admin_moderation_and_settings`, `PATCH /api/ai/admin_moderation_and_settings/:id` | `src/services/settingsService.js`, `src/routes/modules.js`, `public/js/app.js` (Admin view) |

## 9. Project structure

```
src/
  server.js              entry point (HTTP + WebSocket + seed-if-empty)
  app.js                 Express app wiring, static files, error handling
  config.js              environment configuration (.env)
  db/
    schema.sql           full SQLite schema
    database.js          better-sqlite3 connection/open/close helpers
    seed.js              deterministic seed fixtures
  middleware/
    auth.js              session resolution, requireAuth, requireAdmin
    error.js             HttpError, notFound, errorHandler
  services/
    authService (via routes/auth.js)  register/login/logout
    sessionService (in middleware)    SQLite-backed sessions
    auditService.js      audit events
    usageService.js      usage logs + summaries
    settingsService.js   admin settings (blocked terms, model access, defaults)
    chatService.js       chat orchestration, retrieval, tools
    modelAdapter.js      deterministic local LLM response adapter
    fileService.js       multer uploads, metadata, content extraction
  routes/
    auth.js              /api/auth/*
    modules.js           all /api/ai/<module> routes (AI-01..AI-12)
    share.js             /api/share/:token
  realtime/ws.js         WebSocket server + broadcast
public/
  index.html, auth.html, app.html, share.html, css/app.css, js/*.js
scripts/
  db-reset.js, db-seed.js
Dockerfile, docker-compose.yml, .env.example, README.md
```

## 10. Determinism and offline behavior

- Chat responses come from a deterministic local adapter (`modelAdapter.js`) — no external LLM call.
- Knowledge retrieval uses deterministic full-text matching over uploaded/seeded file contents.
- Tool execution is local (safe arithmetic evaluator, local knowledge search).
- Email, payment, object storage, and external HTTP services are not used; all persistence is local
  SQLite and the local filesystem.
- Blocked terms configured by admins are enforced at chat time and audit-logged.
- Errors are deterministic, user-facing messages never include stack traces, and cross-user access
  is rejected with `404`/`403` depending on the module.
