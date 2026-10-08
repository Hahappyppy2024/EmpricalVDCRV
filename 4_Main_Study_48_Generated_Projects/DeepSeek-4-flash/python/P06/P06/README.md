# P06 — Real-Time Team Chat System

A complete, integrated, runnable real-time team chat web application. It is a
**synthetic benchmark system** aligned with the high-level workflow anchors of
**Zulip** (organizations/workspaces, channels, topics, private conversations,
messages, attachments, realtime events, channel administration) but implemented
independently from scratch.

The application implements all 12 use cases (CHAT-01 … CHAT-12) on a single
codebase with shared accounts, sessions, entities, relationships and workflows.

## Technology stack (authoritative)

| Area | Technology |
| --- | --- |
| Language / runtime | Python 3.12 (3.11+ works locally) |
| Web framework | Flask 3 |
| Database | SQLite via SQLAlchemy 2 (repository/data-access layer) |
| Authentication | Server-side sessions stored in SQLite, identified by an HTTP-only cookie |
| Browser client | Server-served HTML, CSS and vanilla JavaScript |
| Real-time transport | Flask-Sock (WebSocket) at `/ws/chat` |
| Dependency tooling | Pinned `requirements.txt` + virtualenv commands |
| Configuration | Environment variables documented in `.env.example` |
| Containerization | `Dockerfile` + `docker-compose.yml` |

## Features by use case

| ID | Use case | What it does |
| --- | --- | --- |
| CHAT-01 | Accounts | Register, sign in, sign out, recover/reset password, manage profile and sessions |
| CHAT-02 | Workspaces and channels | Create workspaces and public/private channels; list/rename them |
| CHAT-03 | Membership lifecycle | Invite, accept, change roles, remove members (owner/admin only) |
| CHAT-04 | Real-time messaging | Send, edit, delete and receive channel messages over WebSocket |
| CHAT-05 | Message history and search | Keyword + date search over visible history; saved searches |
| CHAT-06 | Direct messages | Private 1:1 conversations with read/delivery state and typing |
| CHAT-07 | Attachments | Upload/download files with ownership and private-file rules |
| CHAT-08 | Link preview | Deterministic offline metadata for URLs in messages |
| CHAT-09 | Channel management | Archive/rename/configure channels with an auditable event log |
| CHAT-10 | Connection & message handling | Reconnects, duplicate-send detection, delivery states (sent/delivered/read/failed) |
| CHAT-11 | Frontend API integration | Loading states, typing indicators, empty/error states, presence |
| CHAT-12 | Errors | Stable deterministic user-facing errors + client error records |

## Prerequisites

- Python 3.11 or 3.12
- (Optional) Docker + Docker Compose for the containerized path

## Setup

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1        # Windows PowerShell
# source .venv/bin/activate          # Linux / macOS
pip install -r requirements.txt
```

## Environment configuration

Copy `.env.example` to `.env` and adjust as needed (safe local defaults exist,
so `.env` is optional):

| Variable | Default | Purpose |
| --- | --- | --- |
| `SECRET_KEY` | `dev-secret-change-me` | Signs cookies and deterministic tokens |
| `DATABASE_URL` | `sqlite:///chat.db` | SQLAlchemy database URL (relative SQLite paths resolve to `instance/`; use `sqlite:////abs/path` for elsewhere) |
| `SESSION_COOKIE_NAME` | `chat_session` | HTTP-only session cookie name |
| `SESSION_TTL_SECONDS` | `604800` | Server-side session lifetime |
| `UPLOAD_FOLDER` | `instance/uploads` | Local attachment storage |
| `MAX_UPLOAD_BYTES` | `16777216` | Max upload size (16 MiB) |
| `MAILBOX_DIR` | `instance/mailbox` | Local mail adapter output |
| `PUBLIC_BASE_URL` | `http://127.0.0.1:5000` | Base URL for reset/invitation links |
| `COOKIE_SECURE` | `false` | Require HTTPS on the session cookie |

## Database commands

```powershell
flask reset-db     # drop + recreate + seed (fresh deterministic data)
flask init-db      # create tables only
flask seed         # seed data into an existing database
```

## Startup

```powershell
flask run          # http://127.0.0.1:5000
# or
python run.py
```

Open http://127.0.0.1:5000 and sign in with a seed account.

### Seed accounts

All seed passwords are `password123`.

| Username | Email | Global role | Notes |
| --- | --- | --- | --- |
| `owner` | owner@example.com | owner | Workspace owner of `acme` |
| `admin` | admin@example.com | admin | Can manage channels/members |
| `member` | member@example.com | member | Regular member |
| `alice` | alice@example.com | member | Member of the private `secret` channel |
| `bob` | bob@example.com | member | Member of the private `secret` channel |
| `carol` | carol@example.com | member | Can accept invitation `seed-invitation-carol-001` |

Seed data includes the workspace **Acme Corp** (`acme`), channels `general`,
`announcements`, `random`, `secret` (private) and an archived example channel,
topics (`welcome`, `releases`, …), seeded messages, a DM thread, a public
attachment, a private attachment, link previews, invitations, delivery records,
audit events, sessions, error records and frontend events.

## Docker

```bash
docker compose up --build
# app at http://localhost:8000
```

or manually:

```bash
docker build -t realtime-chat .
docker run -p 8000:8000 -e SECRET_KEY=change-me realtime-chat
```

The container runs gunicorn (gthread worker) which passes the raw socket for
WebSocket upgrades, so `/ws/chat` works in Docker too. Seed the database on
first start inside the container:

```bash
docker compose exec chat flask reset-db
```

## Pages

| URL | Purpose |
| --- | --- |
| `/` `/home` | Landing / dashboard |
| `/login` `/register` `/forgot-password` `/reset-password/<token>` | Account flows |
| `/profile` | Profile + active sessions |
| `/workspaces` | Workspace & channel listing/creation |
| `/chat` | Main chat shell (channels, topics, realtime, presence, search, typing) |
| `/dm` | Direct messages |
| `/attachments` | Attachment upload/list/download |
| `/links` | Link preview |
| `/workspaces/<slug>/members` | Membership lifecycle |
| `/workspaces/<slug>/channels` | Channel management (owner/admin) |
| `/audit?slug=acme` | Audit log (owner/admin) |
| `/connection` | Delivery states + connection events |
| `/frontend` | Frontend API states |
| `/errors` | Error records |
| `/search` | Message history search |

## API overview

Every feature exposes a JSON API under `/api/chat/...`. Success responses are
`{"ok": true, "data": ...}`; failures are `{"ok": false, "error": {"code",
"message"}}` with a stable HTTP status and never leak stack traces.

| Use case | Routes |
| --- | --- |
| CHAT-01 | `GET/POST /api/chat/accounts`, `PATCH /api/chat/accounts/{id}`, `POST /api/chat/accounts/login`, `POST /api/chat/accounts/logout`, `GET /api/chat/accounts/me` |
| CHAT-02 | `GET/POST /api/chat/workspaces_and_channels`, `PATCH /api/chat/workspaces_and_channels/{id}` |
| CHAT-03 | `GET/POST /api/chat/membership_lifecycle`, `PATCH /api/chat/membership_lifecycle/{id}` |
| CHAT-04 | `GET/POST /api/chat/real_time_messaging`, `PATCH /api/chat/real_time_messaging/{id}` |
| CHAT-05 | `GET/POST /api/chat/message_history_and_search`, `PATCH /api/chat/message_history_and_search/{id}` |
| CHAT-06 | `GET/POST /api/chat/direct_messages`, `PATCH /api/chat/direct_messages/{id}` |
| CHAT-07 | `GET/POST /api/chat/attachments`, `PATCH /api/chat/attachments/{id}`, `GET /api/chat/attachments/{id}/download` |
| CHAT-08 | `GET/POST /api/chat/link_preview`, `PATCH /api/chat/link_preview/{id}` |
| CHAT-09 | `GET/POST /api/chat/channel_management`, `PATCH /api/chat/channel_management/{id}`, `GET /api/chat/channel_management/audit` |
| CHAT-10 | `GET/POST /api/chat/connection_and_message_handling`, `PATCH /api/chat/connection_and_message_handling/{id}` |
| CHAT-11 | `GET/POST /api/chat/frontend_api_integration`, `PATCH /api/chat/frontend_api_integration/{id}` |
| CHAT-12 | `GET/POST /api/chat/errors`, `PATCH /api/chat/errors/{id}` |

## WebSocket protocol (`/ws/chat`)

Authenticated with the `chat_session` cookie. Client → server (JSON):

```json
{"type": "subscribe", "workspace_slug": "acme", "channels": [1], "threads": [3]}
{"type": "message", "slug": "acme", "channel_id": 1, "body": "hi", "client_msg_id": "uuid", "topic_id": null}
{"type": "edit", "slug": "acme", "message_id": 1, "body": "edited"}
{"type": "delete", "slug": "acme", "message_id": 1}
{"type": "dm", "recipient": "alice", "body": "hello", "client_msg_id": "uuid"}
{"type": "typing", "channel_id": 1}
{"type": "read", "message_id": 1}
{"type": "ping"}
```

Server → client: `hello`, `subscribed`, `message_new`, `message_edited`,
`message_deleted`, `dm_new`, `dm_edited`, `dm_deleted`, `typing`, `presence`,
`ack`, `pong`, `error`. Duplicate sends are rejected idempotently using
`client_msg_id`, and delivery records move `sent → delivered → read`.

## Deterministic local adapters

The application runs fully offline:

- **Mail** (`app/adapters.py`, `LocalMailbox`): writes every sent message as a
  text file under `instance/mailbox/`.
- **Storage** (`LocalStorage`): stores uploads under `instance/uploads/` with
  content-addressed names (SHA-256 prefix) and records metadata in SQLite.
- **Link preview** (`LocalLinkPreviewFetcher`): returns deterministic metadata
  for any URL (fixtures for known URLs, synthesized metadata otherwise).

## Architecture

```
app/
  __init__.py        app factory (sessions, error handlers, realtime hub, CLI)
  config.py          configuration from environment
  extensions.py      SQLAlchemy + Flask-Sock instances
  models.py          all persistent entities (SQLAlchemy 2 typed models)
  repositories.py    repository / data-access layer
  services.py        business logic, validation and workflow transitions
  serializers.py     JSON serializers for API + realtime payloads
  realtime.py        in-process WebSocket hub (subscriptions + broadcast)
  auth.py            cookie session helpers and guards
  adapters.py        deterministic local mail/storage/link-preview adapters
  errors.py          AppError + stable error handlers
  cli.py             flask init-db / seed / reset-db commands
  seeds.py           deterministic seed fixtures
  blueprints/        feature blueprints (HTML + JSON + WebSocket routes)
  templates/         Jinja templates
  static/            CSS + vanilla JS clients
```

## Traceability table

| Use case | Main implementation files | Main routes / events |
| --- | --- | --- |
| CHAT-01 | `app/services.py` (`AuthService`), `app/blueprints/accounts.py`, `app/templates/login.html`, `register.html`, `profile.html` | `/api/chat/accounts` (`GET/POST/PATCH`), `login`, `logout`, `/login`, `/register`, `/profile` |
| CHAT-02 | `WorkspaceService`, `app/blueprints/workspaces.py`, `templates/workspaces.html` | `/api/chat/workspaces_and_channels` (`GET/POST/PATCH`), `/workspaces` |
| CHAT-03 | `MembershipService`, `app/blueprints/membership.py`, `templates/members.html`, `invitation.html` | `/api/chat/membership_lifecycle` (`GET/POST/PATCH`), `/workspaces/<slug>/members`, `/invitations/<token>` |
| CHAT-04 | `MessagingService`, `app/blueprints/messaging.py`, `app/blueprints/sockets.py`, `static/js/chat.js` | `/api/chat/real_time_messaging` (`GET/POST/PATCH`), WS `message`/`edit`/`delete`, events `message_new`/`message_edited`/`message_deleted` |
| CHAT-05 | `SearchService`, `app/blueprints/history_search.py`, `static/js/chat.js` (search) | `/api/chat/message_history_and_search` (`GET/POST/PATCH`), `/search` |
| CHAT-06 | `DMService`, `app/blueprints/direct_messages.py`, `static/js/dms.js` | `/api/chat/direct_messages` (`GET/POST/PATCH`), WS `dm`/`dm_read`, events `dm_new`/`dm_edited`/`dm_deleted` |
| CHAT-07 | `AttachmentService`, `app/blueprints/attachments.py`, `templates/attachments.html` | `/api/chat/attachments` (`GET/POST/PATCH`), `/api/chat/attachments/{id}/download`, `/attachments` |
| CHAT-08 | `LinkPreviewService`, `app/blueprints/link_preview.py`, `app/adapters.py`, `templates/links.html` | `/api/chat/link_preview` (`GET/POST/PATCH`), `/links` |
| CHAT-09 | `ChannelManagementService`, `app/blueprints/channel_management.py`, `templates/channels_admin.html`, `audit.html` | `/api/chat/channel_management` (`GET/POST/PATCH`, `/audit`), `/workspaces/<slug>/channels`, `/audit` |
| CHAT-10 | `ConnectionService`, `app/blueprints/connection_handling.py`, `app/blueprints/sockets.py`, `static/js/chat.js` | `/api/chat/connection_and_message_handling` (`GET/POST/PATCH`), WS `reconnect`/`duplicate`/`read`, delivery events |
| CHAT-11 | `FrontendService`, `app/blueprints/frontend_api.py`, `static/js/chat.js`, `frontend.js` | `/api/chat/frontend_api_integration` (`GET/POST/PATCH`), `/frontend` |
| CHAT-12 | `ErrorService`, `app/errors.py`, `app/blueprints/errors_api.py`, `static/js/errors.js` | `/api/chat/errors` (`GET/POST/PATCH`), `/errors` |

## Notes on deterministic choices

- Passwords use Werkzeug PBKDF2 (seeded passwords are stable within a run).
- Invitation and reset tokens are derived deterministically from a stable seed
  plus `SECRET_KEY`, so retrying the same request is idempotent.
- Duplicate message sends are detected with `client_msg_id` (unique per sender)
  and return the existing record instead of creating a duplicate.
- WebSocket broadcasts are delivered to subscribers of both the channel key and
  the workspace key when both are subscribed; the browser client de-duplicates
  by message id.
