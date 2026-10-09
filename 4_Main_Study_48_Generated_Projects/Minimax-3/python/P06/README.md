# P06 — Real-Time Team Chat System

A complete, runnable Flask 3 + SQLite + Flask-Sock implementation of the
P06 Real-Time Team Chat System specification (CHAT-01 through CHAT-12).
The project is aligned with the high-level Zulip workflow anchors
(organizations, streams/channels, topics, private conversations,
messages, attachments, realtime events, and channel administration)
without copying code from the real-world reference project.

## Stack

| Concern | Choice |
| --- | --- |
| Language / runtime | Python 3.12 |
| Web framework | Flask 3 |
| Real-time transport | Flask-Sock (WebSocket) |
| ORM | SQLAlchemy 2 |
| Database | SQLite (file-backed, offline) |
| Auth | Server-side sessions, HTTP-only `session` cookie, rows in SQLite |
| Browser client | Server-rendered HTML + vanilla ES module JavaScript |
| Config | `python-dotenv` reading `.env` / `.env.example` |
| Local object storage | `storage/` directory, content addressed |
| Containerization | `Dockerfile` + `docker-compose.yml` |

## Prerequisites

* Python 3.12 or newer
* `pip` and the standard `venv` module (or Docker)
* No paid or external services are required

## Setup

### Option A — Local Python

```bash
cd P06

# 1. Create and activate a virtual environment
python3.12 -m venv .venv
# PowerShell
.venv\Scripts\Activate.ps1
# bash / zsh
source .venv/bin/activate

# 2. Install pinned dependencies
pip install -r requirements.txt

# 3. Copy local env (or edit the existing one)
copy .env.example .env   # Windows
# cp .env.example .env  # bash

# 4. Reset and seed the database (idempotent)
python scripts/reset_db.py
python scripts/seed.py

# 5. Run the application
python wsgi.py
```

The app now listens on `http://localhost:5000`.

### Option B — Docker / Compose

```bash
docker compose build
docker compose run --rm chat python scripts/reset_db.py
docker compose run --rm chat python scripts/seed.py
docker compose up -d
```

When the container starts, gunicorn serves on port 5000.

## Useful commands

| Action | Command |
| --- | --- |
| Reset the SQLite schema | `python scripts/reset_db.py` |
| Seed deterministic fixtures | `python scripts/seed.py` |
| Start dev server | `python wsgi.py` |
| Start with gunicorn (Docker) | `gunicorn --bind 0.0.0.0:5000 wsgi:app` |
| Smoke-check | `curl -s http://localhost:5000/healthz` |
| Reset via Docker | `docker compose run --rm chat python scripts/reset_db.py` |
| Restart Docker stack | `docker compose up -d --build` |

## Seed accounts

| Email | Username | Role | Password | Notes |
| --- | --- | --- | --- | --- |
| admin@example.com | admin | site admin + workspace owner | `AdminPass123!` | Global administrator |
| alice@example.com | alice | workspace admin | `AlicePass123!` | Has messages and DM history |
| bob@example.com | bob | member | `BobPass123!` | Replies in seed messages |
| carol@example.com | carol | guest | `CarolPass123!` | Limited workspace access |

A pending invitation is seeded for `dave@example.com` with token
`seed-invite-token-dave` (works through
`POST /api/chat/membership_lifecycle/invitations/<token>/accept`).

## Configuration (`.env.example`)

| Variable | Purpose |
| --- | --- |
| `FLASK_SECRET_KEY` | Cookie signing key |
| `FLASK_DEBUG` | `1` enables reload + verbose errors |
| `DATABASE_URL` | SQLAlchemy URL (default `sqlite:///data/chat.db`) |
| `STORAGE_DIR` | Directory for uploaded attachments |
| `HOST` / `PORT` | Bind address |
| `SESSION_LIFETIME` | Session cookie lifetime in seconds |

## Project layout

```
P06/
├── app/
│   ├── __init__.py            # app factory
│   ├── config.py              # env-driven configuration
│   ├── db.py                  # SQLAlchemy engine/session helpers
│   ├── errors.py              # service error hierarchy
│   ├── models/                # ORM entities (User, Session, Workspace, …)
│   ├── repositories/          # data access layer
│   ├── services/              # business logic per use case
│   ├── controllers/           # Flask routes + JSON API contract
│   ├── websocket/             # Flask-Sock realtime layer
│   ├── templates/             # Jinja2 pages
│   └── static/                # CSS + JS
├── scripts/
│   ├── reset_db.py            # drops + recreates schema
│   └── seed.py                # deterministic fixtures
├── storage/                   # uploaded attachments
├── data/                      # SQLite database directory
├── wsgi.py                    # entrypoint
├── requirements.txt
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── README.md
```

## Use-case traceability table

| Use case | Title | Routes / events | Main implementation |
| --- | --- | --- | --- |
| CHAT-01 | Accounts | `GET /api/chat/accounts`, `POST /api/chat/accounts`, `POST /api/chat/accounts/signin`, `POST /api/chat/accounts/signout`, `GET /api/chat/accounts/me`, `PATCH /api/chat/accounts/me`, `POST /api/chat/accounts/password/reset/request`, `POST /api/chat/accounts/password/reset`, `GET /api/chat/accounts/sessions` | `app/services/accounts_service.py`, `app/services/session_service.py`, `app/controllers/__init__.py`, `app/templates/signin.html`, `app/templates/register.html`, `app/templates/reset_request.html`, `app/templates/reset_perform.html`, `app/static/js/signin.js`, `app/static/js/register.js`, `app/static/js/reset_*.js` |
| CHAT-02 | Workspaces and channels | `GET /api/chat/workspaces_and_channels`, `POST /api/chat/workspaces_and_channels`, `GET /api/chat/workspaces_and_channels/<id>`, `GET /api/chat/workspaces_and_channels/<id>/channels`, `POST /api/chat/workspaces_and_channels/<id>/channels` | `app/services/workspaces_service.py`, `app/repositories/workspace_repo.py`, `app/models/entities.py`, `app/static/js/dashboard.js` |
| CHAT-03 | Membership lifecycle | `GET /api/chat/workspaces_and_channels/<id>/members`, `POST .../members`, `PATCH .../members/<uid>`, `DELETE .../members/<uid>`, `POST /api/chat/membership_lifecycle/<id>/leave`, `GET /api/chat/membership_lifecycle/<id>/invitations`, `POST /api/chat/membership_lifecycle/invitations/<token>/<action>` | `app/services/membership_service.py`, `app/repositories/membership_repo.py`, `app/controllers/__init__.py`, dashboard.js |
| CHAT-04 | Real-time messaging | `GET /api/chat/real_time_messaging/<channel_id>/messages`, `POST .../messages`, `PATCH /api/chat/real_time_messaging/<id>`, `DELETE .../id`, `ws://…/ws/chat` (events: `message`, `message.created`) | `app/services/messaging_service.py`, `app/repositories/message_repo.py`, `app/websocket/__init__.py`, `app/static/js/dashboard.js` |
| CHAT-05 | Message history and search | `GET /api/chat/message_history_and_search`, `POST /api/chat/message_history_and_search` | `app/services/search_service.py`, dashboard.js search form |
| CHAT-06 | Direct messages | `GET /api/chat/direct_messages`, `GET /api/chat/direct_messages/<uid>`, `POST /api/chat/direct_messages/<uid>`, `POST /api/chat/direct_messages/<thread_key>/read` | `app/services/direct_messages_service.py`, `app/repositories/message_repo.py`, `app/templates/dm.html`, `app/static/js/dm.js` |
| CHAT-07 | Attachments | `GET /api/chat/attachments`, `POST /api/chat/attachments`, `GET /api/chat/attachments/<id>/download` | `app/services/attachments_service.py`, `app/repositories/message_repo.py`, dashboard.js file input |
| CHAT-08 | Link preview | `GET /api/chat/link_preview`, `POST /api/chat/link_preview` | `app/services/link_preview_service.py`, `app/repositories/extras_repo.py`, dashboard.js `/preview` command |
| CHAT-09 | Channel management | `GET /api/chat/channel_management/<channel_id>`, `PATCH .../<id>`, `POST .../<id>/archive`, `POST .../<id>/unarchive`, `POST .../<id>/rename`, `GET /api/chat/channel_management/<workspace_id>/audit` | `app/services/channel_management_service.py`, `app/repositories/extras_repo.py`, dashboard.js admin panel |
| CHAT-10 | Connection and message handling | `GET /api/chat/connection_and_message_handling/connections`, `POST .../connections`, `POST .../connections/<id>/events`, `DELETE .../connections/<id>`, plus WebSocket `ping`, `pong`, `subscribe`, `unsubscribe`, `ack` | `app/services/connection_service.py`, `app/websocket/__init__.py`, dashboard.js reconnect logic |
| CHAT-11 | Frontend API integration | `GET /api/chat/frontend_api_integration`, `POST /api/chat/frontend_api_integration` | `app/services/frontend_api_service.py`, `app/static/js/app.js` (`postState`, `loading`/`success`/`error`/typing indicators), page-level handlers in dashboard.js / dm.js |
| CHAT-12 | Errors | `GET /api/chat/errors`, `POST /api/chat/errors`, global `ServiceError` handler, frontend `reportError` | `app/services/errors_service.py`, `app/errors.py`, `app/controllers/__init__.py` (errorhandler), `app/static/js/app.js` |

## Browser workflow overview

1. Visit `http://localhost:5000` → redirected to `/signin`.
2. Sign in with one of the seed accounts.
3. The dashboard (`/app`) lists workspaces, channels, members,
   invitations, audit events, search, link preview, attachments, channel
   administration, and realtime connection diagnostics.
4. The DM page (`/app/dm`) lists DM threads and allows private
   conversations with other users.
5. The admin page (`/app/admin`, only for `is_admin`) lists server-side
   errors, sessions, frontend state reports, and audit events.
6. All pages automatically POST frontend state reports
   (`loading` / `success` / `error` / `empty` / `typing`) to
   `POST /api/chat/frontend_api_integration`.

## Notes

* Email, payment, link preview, and external HTTP services are all
  represented by deterministic local adapters. No outbound network calls
  are required.
* File uploads are validated for type and size, stored under
  `storage/` with content-addressed filenames, and served through an
  owner-checked download route.
* Passwords use `werkzeug.security` PBKDF2 hashes and account reset uses
  single-use tokens with deterministic expiration.
* The event bus in `app/services/event_bus.py` keeps the realtime layer
  decoupled from the request-response controllers.
* Frontend modules are served as native ES modules under
  `static/js/`; there is no build step.
