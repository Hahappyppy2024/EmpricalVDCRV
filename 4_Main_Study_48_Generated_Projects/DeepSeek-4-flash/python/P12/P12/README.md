# P12 — Data Analytics Dashboard

A complete, runnable data analytics web application implemented from the
authoritative Python Technology profile (Python 3.12, Flask 3, SQLite via
SQLAlchemy 2 repository layer, server-side session authentication, server-served
HTML/CSS/vanilla JS). It implements all twelve use cases DATA-01 … DATA-12:
account access, dataset upload, dataset catalog, data preview, filter builder,
chart builder, calculated columns, dashboard sharing, export, data source
connections, audit and lineage, and admin operations.

Everything runs offline. Email, object storage, and data-source adapters are
deterministic local adapters; no paid service or external account is required.

---

## Prerequisites

- Python 3.12 (3.10–3.12 also work with the pinned dependencies)
- Docker (optional, for the containerized run)
- On Windows, PowerShell is used for the commands below.

## 1. Dependency installation

Create a virtual environment and install the pinned dependencies:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

On macOS/Linux use `source .venv/bin/activate`.

## 2. Environment configuration

Copy the example environment file and adjust if needed:

```powershell
Copy-Item .env.example .env
```

Supported variables (all documented in `.env.example`):

| Variable | Default | Description |
| --- | --- | --- |
| `FLASK_SECRET_KEY` | `dev-secret-change-me` | Flask secret key |
| `DATABASE_PATH` | `data/analytics.db` | SQLite database file |
| `UPLOAD_DIR` | `data/uploads` | Local file storage for uploads/exports |
| `MAX_UPLOAD_MB` | `10` | Maximum upload size in MB |
| `PORT` | `8000` | HTTP port for `python run.py` |

Environment variables always win over `.env` values when both are set.

## 3. Database reset and seed

Reset the SQLite database and load the deterministic seed fixtures:

```powershell
flask --app run:app init-db
```

or, equivalently:

```powershell
python -m app.seed
```

The seed creates 3 users, 2 datasets (CSV + JSON), catalog entries, previews,
filters, charts, calculated columns, shares, exports (CSV + PDF), data-source
connections, audit/lineage records, admin operations, sessions, and settings.

### Seed accounts

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `admin123` |
| Analyst | `analyst` | `analyst123` |
| Viewer | `viewer` | `viewer123` |

## 4. Startup

```powershell
flask --app run:app run --host 0.0.0.0 --port 8000
```

or:

```powershell
python run.py
```

Open http://localhost:8000 and sign in with one of the seed accounts.

## 5. Docker

```powershell
docker compose up --build
```

This builds the image, runs `init-db`, and serves the app on
http://localhost:8000. To stop: `docker compose down`.

## 6. Usage overview

- **Account access** (`/account`, `/login`, `/signup`): sign-in, registration,
  sign-out, session revocation, and access log.
- **Dataset upload** (`/datasets/upload`): upload CSV/JSON; schema detection and
  parsed-row storage are performed server-side.
- **Dataset catalog** (`/datasets`): search by name/description/tags, filter by
  tag, and manage catalog entries. Public/shared entries of other analysts are
  visible; private ones are not.
- **Data preview** (`/datasets/<id>/preview`): column types, summary statistics,
  missing values, and a row preview.
- **Filter builder** (`/filters`): build and save filter expressions (e.g.
  `sales > 200 AND region == "East"`) with a live match preview.
- **Chart builder** (`/charts`): bar/line/pie charts from a dataset, X/Y column
  and aggregation (sum/count/avg/min/max), with live SVG preview.
- **Calculated columns** (`/calculated`): whitelisted expressions (arithmetic,
  `abs`, `round`, `upper`, `lower`, `len`) producing new columns, with live sample.
- **Dashboard sharing** (`/sharing`): share a dashboard with a team or via a
  public link; public links are opened at `/shared/<token>` without sign-in.
- **Export** (`/exports`): generate CSV or PDF exports (optionally filtered) and
  download them.
- **Data source connections** (`/sources`): configure mock database / mock API
  sources, test the connection deterministically, and import snapshots as datasets.
- **Audit and lineage** (`/audit`): every import, transformation, and export plus
  the full audit-event log.
- **Admin operations** (`/admin`, admin only): manage users (enable/disable,
  change roles), run session/audit retention, and update dataset/upload limits.

### API contract

Every use case exposes its HTTP contract:

```
GET   /api/data/<slug>
POST  /api/data/<slug>
PATCH /api/data/<slug>/<id>
```

where `<slug>` is one of `account_access`, `dataset_upload`, `dataset_catalog`,
`data_preview`, `filter_builder`, `chart_builder`, `calculated_columns`,
`dashboard_sharing`, `export`, `data_source_connections`, `audit_and_lineage`,
`admin_operations`. Additional browser-facing endpoints:

```
GET /api/data/dataset_rows?dataset_id=<id>
GET /api/data/filter_builder/preview?dataset_id=<id>&expression=<expr>
GET /api/data/chart_builder/preview?dataset_id=<id>&x=<c>&y=<c>&agg=<a>
GET /api/data/calculated_columns/preview?dataset_id=<id>&expression=<expr>
GET /api/data/export/download/<export_id>
```

Authentication uses an HTTP-only `dash_session_token` cookie backed by persistent
session rows in SQLite (Flask's own signed `session` cookie is used only for
one-off flash messages and never carries authentication state). Unauthenticated
API calls return `401`; unauthorized roles return `403`.

## 7. Use-case traceability

| Use case | Title | Main implementation files / routes |
| --- | --- | --- |
| DATA-01 | Account access | `app/services.py` (`AccountService`), `app/pages.py` (`login`, `signup`, `logout`, `account`), `app/api.py` (`account_access`), `app/models.py` (`User`, `SessionRecord`, `AccountAccess`), `templates/login.html`, `signup.html`, `account.html` |
| DATA-02 | Dataset upload | `app/services.py` (`DatasetService.upload`), `app/pages.py` (`dataset_upload`), `app/api.py` (`dataset_upload`), `app/models.py` (`DatasetUpload`, `StoredFile`, `Dataset`), `templates/dataset_upload.html` |
| DATA-03 | Dataset catalog | `app/services.py` (`DatasetService.visible_catalog`, `create_catalog_entry`, `update_catalog_entry`), `app/pages.py` (`datasets`), `app/api.py` (`dataset_catalog`), `app/models.py` (`DatasetCatalog`), `templates/datasets.html` |
| DATA-04 | Data preview | `app/services.py` (`DatasetService.preview`, `dataset_rows`), `app/pages.py` (`dataset_preview`), `app/api.py` (`data_preview`, `dataset_rows`), `app/analytics.py` (`summarize`), `app/models.py` (`DataPreview`), `templates/preview.html` |
| DATA-05 | Filter builder | `app/services.py` (`FilterService`), `app/api.py` (`filter_builder`, `filter_preview`), `app/analytics.py` (`apply_filter`), `app/models.py` (`FilterBuilder`), `templates/filters.html`, `static/app.js` |
| DATA-06 | Chart builder | `app/services.py` (`ChartService`), `app/api.py` (`chart_builder`, `chart_preview`), `app/analytics.py` (`aggregate_chart`), `app/models.py` (`ChartBuilder`), `templates/charts.html`, `static/app.js` |
| DATA-07 | Calculated columns | `app/services.py` (`CalculatedService`), `app/api.py` (`calculated_columns`, `calculated_preview`), `app/analytics.py` (`validate_calc_expression`), `app/models.py` (`CalculatedColumns`), `templates/calculated.html` |
| DATA-08 | Dashboard sharing | `app/services.py` (`SharingService`), `app/pages.py` (`sharing`, `shared`), `app/api.py` (`dashboard_sharing`), `app/models.py` (`DashboardSharing`), `templates/sharing.html`, `shared.html` |
| DATA-09 | Export | `app/services.py` (`ExportService`), `app/api.py` (`export`, `export_download`), `app/analytics.py` (`build_csv`, `build_pdf`), `app/models.py` (`Export`, `StoredFile`), `templates/exports.html` |
| DATA-10 | Data source connections | `app/services.py` (`DataSourceService`), `app/api.py` (`data_source_connections`), `app/analytics.py` (`mock_source_data`), `app/models.py` (`DataSourceConnections`), `templates/sources.html` |
| DATA-11 | Audit and lineage | `app/services.py` (`AuditService`, `record_event`, `record_lineage`), `app/api.py` (`audit_and_lineage`), `app/models.py` (`AuditAndLineage`, `AuditEvent`), `templates/audit.html` |
| DATA-12 | Admin operations | `app/services.py` (`AdminService`), `app/api.py` (`admin_operations`), `app/pages.py` (`admin_page`), `app/models.py` (`AdminOperations`, `Setting`), `templates/admin.html` |

## 8. Project layout

```
run.py                  Entry point
app/
  __init__.py           App factory, init-db CLI
  config.py             Environment configuration
  extensions.py         SQLAlchemy engine/session bootstrap
  models.py             All persistent entities + serializer
  data_access.py        Repository / data-access layer
  analytics.py          Schema detection, filters, expressions, charts, exports, mock sources
  services.py           Business services for all 12 use cases
  auth.py               Session authentication helpers
  pages.py              Server-served page routes
  api.py                /api/data/<slug> controllers
  seed.py               Deterministic seed fixtures
  templates/            Jinja pages (one per workflow)
  static/               style.css + app.js (vanilla JS)
data/                   SQLite database + local file storage (uploads/exports)
Dockerfile, docker-compose.yml, requirements.txt, .env.example, README.md
```
