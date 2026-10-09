# P12 — Data Analytics Dashboard

Synthetic benchmark project aligned with D-Tale. Implements dataset upload,
data preview, filter builder, chart builder, calculated columns, dashboard
sharing, exports, data source connections, audit & lineage, and admin
operations on top of Python 3.12, Flask 3, and SQLAlchemy 2 with SQLite.

## 1. Prerequisites

- Python 3.12 (CPython)
- `pip` 23+
- Optional: Docker 24+ and Docker Compose v2 for the container workflow
- Optional: `python -m venv` to keep dependencies isolated

## 2. Setup

```bash
# from the project root
python -m venv .venv
# Windows
.venv\Scripts\activate
# Linux / macOS
source .venv/bin/activate

pip install -r requirements.txt
cp .env.example .env   # on Windows use: copy .env.example .env
```

`SECRET_KEY` in `.env` controls Flask session signing. The other variables map
to `app/config.py` and are documented inline.

## 3. Database — initialize / reset

The SQLite database lives at `data/app.db`. The init script is deterministic
and creates seed users, datasets, filters, calculated columns, charts, shares,
and data sources.

```bash
python scripts/init_db.py            # default: reset and seed
python scripts/init_db.py --no-reset # only seed if database missing
python scripts/reset_db.py           # alias for `init_db.py` with reset
```

Seed accounts:

| Username | Password   | Role    |
|----------|------------|---------|
| admin    | Admin#123  | admin   |
| analyst  | Analyst#123| analyst |
| analyst2 | Analyst#123| analyst |
| viewer   | Viewer#123 | viewer  |

Two datasets are seeded:

- `Quarterly Sales` (CSV, owned by `analyst`, team-visible)
- `Service Logs` (JSON, owned by `analyst2`, public)

## 4. Start the development server

```bash
python run.py
# or, equivalently:
flask --app run:app run --host=127.0.0.1 --port=5000
```

Visit http://127.0.0.1:5000 — the application redirects unauthenticated users
to `/login`. After signing in with one of the seed accounts you land on the
role-specific dashboard.

## 5. Docker

```bash
docker build -t data-analytics-dashboard:p12 .
docker compose up --build
# default compose exposes port 5000 on the host
```

The Dockerfile runs `python scripts/init_db.py` before launching the Flask
server, so the SQLite file and seed data are created on first start. The
`./data` directory is bind-mounted so persisted data survives container
restarts.

Stop and remove containers with:

```bash
docker compose down
```

## 6. Use case traceability

| Use case | Primary routes | Files |
|----------|----------------|-------|
| DATA-01 Account access | `GET/POST/PATCH /api/data/account_access` | `app/api/account_access.py`, `app/services/auth_service.py`, `app/templates/login.html` |
| DATA-02 Dataset upload | `GET/POST/PATCH/DELETE /api/data/dataset_upload[/{id}]`, `GET /api/data/dataset_upload/{id}/file` | `app/api/dataset_upload.py`, `app/services/dataset_service.py`, `app/templates/dataset_upload.html` |
| DATA-03 Dataset catalog | `GET/POST/PATCH /api/data/dataset_catalog[/{id}]` | `app/api/dataset_catalog.py`, `app/templates/datasets.html`, `app/static/js/dataset_catalog.js` |
| DATA-04 Data preview | `GET/POST /api/data/data_preview`, `ws://…/ws/data_preview` | `app/api/data_preview.py`, `app/services/expression_engine.py`, `app/templates/dataset_detail.html` |
| DATA-05 Filter builder | `GET/POST/PATCH/DELETE /api/data/filter_builder[/{id}]`, `POST /api/data/filter_builder/run` | `app/api/filter_builder.py`, `app/services/expression_engine.py` |
| DATA-06 Chart builder | `GET/POST/PATCH/DELETE /api/data/chart_builder[/{id}]`, `ws://…/ws/chart_builder` | `app/api/chart_builder.py`, `app/services/chart_service.py` |
| DATA-07 Calculated columns | `GET/POST/PATCH/DELETE /api/data/calculated_columns[/{id}]` | `app/api/calculated_columns.py`, `app/services/expression_engine.py` |
| DATA-08 Dashboard sharing | `GET/POST/PATCH/DELETE /api/data/dashboard_sharing[/{id}]`, `GET /api/data/dashboard_sharing/public/{token}` | `app/api/dashboard_sharing.py`, `app/templates/dashboard_detail.html` |
| DATA-09 Export | `GET/POST/PATCH /api/data/export[/{id}]`, `GET /api/data/export/{id}/download` | `app/api/export.py`, `app/services/export_service.py` |
| DATA-10 Data source connections | `GET/POST/PATCH/DELETE /api/data/data_source_connections[/{id}]`, `POST /api/data/data_source_connections/{id}/probe` | `app/api/data_source_connections.py`, `app/templates/data_sources.html` |
| DATA-11 Audit and lineage | `GET/POST /api/data/audit_and_lineage` | `app/api/audit_and_lineage.py`, `app/services/audit_service.py`, `app/templates/audit.html` |
| DATA-12 Admin operations | `GET/POST/PATCH /api/data/admin_operations[/{id}]`, `GET /api/data/admin_operations/users` | `app/api/admin_operations.py`, `app/templates/admin.html` |

## 7. Project layout

```
.
├── app/
│   ├── __init__.py          # Flask application factory and page routes
│   ├── api/                 # Blueprints per use case (DATA-01 .. DATA-12)
│   ├── services/            # Business logic, expression engine, auth, audit
│   ├── models.py            # SQLAlchemy ORM models (User, Dataset, …)
│   ├── extensions.py        # Shared Flask extension instances
│   ├── config.py            # Environment-driven configuration
│   ├── utils.py             # Validation, error helpers, decorators
│   ├── ws_routes.py         # Flask-Sock WebSocket routes
│   ├── templates/           # Server-rendered HTML pages
│   └── static/              # CSS and vanilla JS modules
├── scripts/
│   ├── init_db.py           # Create schema + seed deterministic fixtures
│   └── reset_db.py          # Convenience wrapper around init_db
├── data/                    # SQLite db, uploads, exports (created at runtime)
├── run.py                   # Entry point used by `python run.py`
├── requirements.txt
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── README.md
```

## 8. Smoke check

After running `python scripts/init_db.py`, start the server (`python run.py`)
and execute:

```bash
# sign in
curl -i -c cookies.txt -b cookies.txt \
     -H "Content-Type: application/json" \
     -d '{"mode":"signin","username":"analyst","password":"Analyst#123"}' \
     http://127.0.0.1:5000/api/data/account_access

# list datasets
curl -b cookies.txt http://127.0.0.1:5000/api/data/dataset_catalog

# preview the first dataset (id 1 by default)
curl -b cookies.txt "http://127.0.0.1:5000/api/data/data_preview?dataset_id=1&limit=5"
```

A successful response is a JSON object of the shape `{ "data": ..., "status": "ok" }`.

## 9. Notes on deterministic local adapters

- Email, payment, and storage providers are not required by the use cases; the
  application stores all artefacts on the local filesystem under `data/`.
- Expression evaluation (`app/services/expression_engine.py`) uses an AST
  whitelist. Function calls, attribute access, and arbitrary subscripts are
  rejected before evaluation, so expressions are safe to accept from signed-in
  users.
- WebSocket routes (`/ws/data_preview`, `/ws/chart_builder`) only echo
  authenticated events to demonstrate the Flask-Sock wiring; the rest of the
  application works fully over plain HTTP.
