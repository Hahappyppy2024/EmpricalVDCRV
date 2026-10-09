# P10 — AI Assistant / LLM WebUI

Synthetic benchmark project aligned with the workflow anchors of **Lobe Chat**:
conversations, model/provider configuration, prompt templates, plugins/tools,
retrieval collections, API keys, sharing, and audit logs.

The project is implemented with the **JavaScript / Node.js 22 LTS** stack
required by the authoritative Technology profile. Every one of the twelve use
cases (AI-01 through AI-12) is wired into a single integrated application with
shared accounts, sessions, entities, and ownership boundaries.

---

## Stack at a glance

| Concern | Choice |
| --- | --- |
| Language | JavaScript (ECMAScript modules) |
| Runtime | Node.js 22 LTS |
| Web framework | Express 5 |
| Database | SQLite via `better-sqlite3` (file: `data/app.db`) |
| Sessions | Server-side session records in SQLite, HTTP-only cookie `aiassist_sid` |
| Real-time | `ws` WebSocket server at `/ws/chat` |
| Browser client | Server-served HTML, CSS, vanilla JavaScript (`public/`) |
| Package tooling | npm with pinned direct dependencies in `package.json` |
| Local model | Deterministic local adapter — no external LLM credentials required |

---

## Repository layout

```
.
├── Dockerfile
├── docker-compose.yml
├── package.json
├── .env.example
├── public/                # Static browser client (HTML / CSS / JS)
│   ├── index.html         # Sign-in + registration
│   ├── dashboard.html
│   ├── chat.html          # WebSocket chat playground
│   ├── conversations.html
│   ├── templates.html
│   ├── models.html
│   ├── knowledge.html
│   ├── collections.html
│   ├── plugins.html
│   ├── api-keys.html
│   ├── shares.html
│   ├── audit.html
│   ├── admin.html
│   ├── shared.html        # Public share viewer
│   └── css/, js/
├── scripts/
│   ├── reset-db.js        # Drop + re-create schema and seed fixtures
│   ├── start.js           # Boot the Express + ws server
│   └── smoke-check.js     # End-to-end smoke check (no test framework)
└── src/
    ├── server.js          # buildApp() + startServer()
    ├── config.js          # Env config loader
    ├── db/
    │   ├── connection.js  # better-sqlite3 singleton
    │   ├── schema.js      # Idempotent CREATE TABLE statements
    │   └── seed.js        # Deterministic seed fixtures
    ├── middleware/
    │   ├── auth.js        # requireAuth / requireRole
    │   └── error.js       # AppError → JSON
    ├── services/
    │   ├── session.js     # SQLite-backed sessions + cookie helpers
    │   ├── audit.js       # audit_events + usage_records
    │   ├── llm.js         # Deterministic local model adapter
    │   ├── storage.js     # Upload directory + checksum helpers
    │   └── validation.js
    ├── modules/           # One module per use case
    │   ├── account_access.js
    │   ├── conversation_management.js
    │   ├── prompt_templates.js
    │   ├── model_configuration.js
    │   ├── knowledge_file_upload.js
    │   ├── retrieval_collection.js
    │   ├── tool_plugin_registry.js
    │   ├── api_key_management.js
    │   ├── chat_execution.js
    │   ├── share_conversation.js
    │   ├── usage_and_audit_logs.js
    │   └── admin_moderation_and_settings.js
    ├── routes/            # Express routers (one per module)
    └── ws/
        └── chat.js        # /ws/chat streaming
```

---

## Prerequisites

- Node.js 22 LTS (`node --version` should print `v22.x`)
- npm 10 or newer (ships with Node 22)
- Optional: Docker 24+ and Docker Compose v2 for the containerised path

---

## Quick start (local Node)

```bash
# 1. Install pinned dependencies
npm install

# 2. Copy environment defaults
copy .env.example .env   # Windows
# or: cp .env.example .env

# 3. Reset the SQLite database and seed deterministic fixtures
npm run reset-db

# 4. Start the server
npm start

# 5. Open the browser client
#    http://127.0.0.1:3000
```

A successful boot prints:

```
[boot] seeded default users + fixtures
[server] listening on http://127.0.0.1:3000
```

---

## Seed accounts

The reset script inserts deterministic fixtures so the application is
immediately usable.

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@local.test` | `Admin#12345` |
| User | `alice@local.test` | `User#12345` |
| User | `bob@local.test` | `User#12345` |

Seed data also includes:

- Two conversations (one per user) with sample messages.
- Three prompt templates including a shared `Summarize meeting` template.
- Four tool plugins: `retriever`, `calculator`, `translator`, `time`.
- Two API key records with masked secrets.
- Two knowledge files (one shared as seed content for retrieval).
- One retrieval collection containing indexed chunks.
- One share link pointing at Alice's conversation.
- Admin settings, blocked terms, and a sample moderation entry.
- Audit + usage records so the dashboard shows data on first sign-in.

---

## Database commands

| Command | Purpose |
| --- | --- |
| `npm run reset-db` | Drop the database file, recreate schema, and seed deterministic fixtures. |
| `npm run seed` | Alias for `npm run reset-db -- --seed`. |

The script is idempotent: running it again wipes the file under `data/app.db`
and rebuilds it. To skip seeding, run `node scripts/reset-db.js --no-seed`.

---

## Startup commands

| Command | Purpose |
| --- | --- |
| `npm start` | Production-style start (`scripts/start.js`). |
| `npm run dev` | `node --watch src/server.js` for live reloads during development. |

The HTTP server binds to `HOST:PORT` from `.env` (default
`127.0.0.1:3000`). Set `HOST=0.0.0.0` to accept traffic from other hosts.

---

## Docker commands

```bash
# Build and run via Compose
docker compose up --build

# Stop
docker compose down

# Reset the database (re-seeds fixtures)
docker compose run --rm app node scripts/reset-db.js

# Inspect logs
docker compose logs -f app
```

The Compose stack persists the database and uploads under the named volume
`app-data` (mapped to `/app/data` inside the container).

---

## Smoke check

```bash
npm run reset-db        # reset database
npm start &             # start in background
npm run smoke           # run scripts/smoke-check.js
```

`scripts/smoke-check.js` exercises six end-to-end flows (health, sign-in,
private list, chat execution, template visibility, and role enforcement) and
exits non-zero if any fails.

---

## Environment variables

| Variable | Default | Notes |
| --- | --- | --- |
| `PORT` | `3000` | HTTP listen port. |
| `HOST` | `127.0.0.1` | HTTP bind host. |
| `SESSION_COOKIE_NAME` | `aiassist_sid` | HTTP-only session cookie name. |
| `SESSION_LIFETIME_SECONDS` | `43200` | Session TTL (12 hours). |
| `DB_PATH` | `data/app.db` | SQLite file path. |
| `UPLOAD_DIR` | `data/uploads` | Knowledge file storage root. |
| `MAX_UPLOAD_BYTES` | `5242880` | Max upload size (5 MiB). |
| `ALLOWED_UPLOAD_EXT` | `.txt,.md,.markdown,.csv,.json,.log` | Allowed knowledge extensions. |
| `DEFAULT_MODEL_ID` | `local-bench-1` | Identifier of the local deterministic model. |
| `DEFAULT_TEMPERATURE` | `0.2` | Default temperature for new model configs. |
| `DEFAULT_CONTEXT_LENGTH` | `2048` | Default context length for new model configs. |
| `SHARE_ALLOWED_ORIGINS` | _(empty)_ | Optional CORS allow-list for the public share viewer. |
| `NODE_ENV` | `development` | Standard Node.js environment marker. |

---

## Use case → implementation traceability

| Use case | Routes / endpoints | Main files |
| --- | --- | --- |
| AI-01 Account access | `GET /api/ai/account_access`, `POST /api/ai/account_access`, `PATCH /api/ai/account_access/:id`, `POST /api/ai/auth/{sign-in,sign-out,register,reset}` | `src/modules/account_access.js`, `src/routes/account.js`, `src/services/session.js`, `src/middleware/auth.js`, `public/index.html` |
| AI-02 Conversation management | `GET /api/ai/conversation_management`, `POST`, `PATCH /:id`, `GET /api/ai/conversations/:id`, `POST /api/ai/conversations/:id/messages` | `src/modules/conversation_management.js`, `src/routes/conversations.js`, `public/conversations.html`, `public/chat.html` |
| AI-03 Prompt templates | `GET /api/ai/prompt_templates`, `POST`, `PATCH /:id`, `DELETE /:id` | `src/modules/prompt_templates.js`, `src/routes/prompt_templates.js`, `public/templates.html` |
| AI-04 Model configuration | `GET /api/ai/model_configuration`, `POST`, `PATCH /:id`, `DELETE /:id` | `src/modules/model_configuration.js`, `src/routes/model_configuration.js`, `public/models.html` |
| AI-05 Knowledge file upload | `GET /api/ai/knowledge_file_upload`, `POST` (multipart `file`), `PATCH /:id`, `DELETE /:id`, `GET /:id/content` | `src/modules/knowledge_file_upload.js`, `src/routes/knowledge_files.js`, `src/services/storage.js`, `public/knowledge.html` |
| AI-06 Retrieval collection | `GET /api/ai/retrieval_collection`, `POST`, `PATCH /:id`, `DELETE /:id`, `GET /:id`, `POST /:id/files`, `DELETE /:id/files/:file_id`, `POST /:id/search` | `src/modules/retrieval_collection.js`, `src/routes/retrieval_collections.js`, `public/collections.html` |
| AI-07 Tool/plugin registry | `GET /api/ai/tool_plugin_registry`, `POST`, `PATCH /:id`, `DELETE /:id`, `POST /:id/toggle` | `src/modules/tool_plugin_registry.js`, `src/routes/tool_plugins.js`, `public/plugins.html` |
| AI-08 API key management | `GET /api/ai/api_key_management`, `POST`, `PATCH /:id` (rotate), `DELETE /:id` (revoke) | `src/modules/api_key_management.js`, `src/routes/api_keys.js`, `public/api-keys.html` |
| AI-09 Chat execution | `GET /api/ai/chat_execution`, `POST` (sync), `PATCH/GET /:id`, WebSocket `ws://host/ws/chat` | `src/modules/chat_execution.js`, `src/routes/chat_execution.js`, `src/services/llm.js`, `src/ws/chat.js`, `public/chat.html` |
| AI-10 Share conversation | `GET /api/ai/share_conversation`, `POST`, `PATCH /:id` (revoke), public `GET /api/ai/public/share/:token` | `src/modules/share_conversation.js`, `src/routes/share_conversation.js`, `public/shares.html`, `public/shared.html` |
| AI-11 Usage and audit logs | `GET /api/ai/usage_and_audit_logs`, `POST`, `PATCH /:id` | `src/modules/usage_and_audit_logs.js`, `src/services/audit.js`, `src/routes/usage_audit.js`, `public/audit.html` |
| AI-12 Admin moderation and settings | `GET /api/ai/admin_moderation_and_settings`, `POST`, `PATCH /:id`, plus `/api/ai/admin/{users,settings,blocked_terms,moderation}` | `src/modules/admin_moderation_and_settings.js`, `src/routes/admin.js`, `public/admin.html` |

---

## Browser workflow tour

1. Sign in at `http://127.0.0.1:3000/` with one of the seed accounts.
2. The dashboard summarises conversations, templates, files, and plugins.
3. Open `Chat` to send a prompt; the assistant reply streams over WebSocket
   (deterministic local model, no external network needed).
4. `Knowledge` uploads a `.txt`/`.md`/`.csv`/`.json`/`.log` file (max 5 MiB).
5. `Collections` attaches a file to a retrieval collection and searches its
   chunks to ground the next chat response.
6. `Plugins` lets users toggle the `retriever` plugin and admins register new
   tool identifiers.
7. `API Keys` stores a masked, salted secret; rotation replaces the hash.
8. `Shares` issues a tokenised link at `/share/<token>` — open it in an
   incognito window to confirm the public viewer works without a cookie.
9. `Usage & Audit` lists personal token usage; admins see all events.
10. `Admin` (admin role only) manages settings, blocked terms, and user
    moderation.

---

## Deterministic local model

`src/services/llm.js` synthesises responses without contacting any external
service. It honours:

- Retrieval grounding: when a chat references RAG keywords or a retrieval
  collection is supplied, the response cites the top matching chunks.
- Token accounting: prompt and completion tokens are approximated by
  character length so `usage_records` always reflects real work.
- Safety modes: `strict` adds a concise-system prompt, `standard` uses the
  conversation default, `off` disables safety framing.

This keeps the project fully offline while preserving the observable
behaviours required by the use cases.

---

## License

MIT (synthetic benchmark project).
