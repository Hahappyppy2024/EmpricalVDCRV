"""Business services for the P12 Data Analytics Dashboard.

Each use case maps to a service module section. Services raise ValueError
with deterministic messages; controllers translate them into JSON responses.
"""
import os
import uuid
from datetime import datetime

from werkzeug.security import check_password_hash, generate_password_hash

from . import analytics
from . import models
from .data_access import Repository

AUDIT = "audit"


# ---------------------------------------------------------------------------
# Audit helpers (DATA-11)
# ---------------------------------------------------------------------------

def record_event(repo: Repository, user, action, entity_type="", entity_id=None, detail=""):
    repo.add(
        models.AuditEvent(
            user_id=user.id,
            action=action,
            entity_type=entity_type,
            entity_id=entity_id,
            detail=detail,
        )
    )


def record_lineage(repo, user, operation, dataset_id=None, source_ref="", output_ref="", detail=""):
    repo.add(
        models.AuditAndLineage(
            user_id=user.id,
            dataset_id=dataset_id,
            operation=operation,
            source_ref=source_ref,
            output_ref=output_ref,
            detail=detail,
        )
    )


# ---------------------------------------------------------------------------
# DATA-01  Account access
# ---------------------------------------------------------------------------

class AccountService:
    def __init__(self, repo: Repository, config):
        self.repo = repo
        self.config = config

    def signup(self, username, email, password):
        username = (username or "").strip()
        email = (email or "").strip()
        if not username or not email or not password:
            raise ValueError("username, email, and password are required")
        if len(password) < 8:
            raise ValueError("password must be at least 8 characters")
        if self.repo.user_by_username(username):
            raise ValueError("username is already registered")
        if self.repo.user_by_email(email):
            raise ValueError("email is already registered")
        user = self.repo.add(
            models.User(
                username=username,
                email=email,
                password_hash=generate_password_hash(password),
                role=models.ROLE_VIEWER,
                enabled=True,
            )
        )
        self.repo.flush()
        self.repo.add(
            models.AccountAccess(
                user_id=user.id, action="signup", outcome="success", detail=username
            )
        )
        record_event(self.repo, user, "auth.signup", "user", user.id, username)
        return user

    def signin(self, username, password):
        username = (username or "").strip()
        user = self.repo.user_by_username(username)
        if not user or not check_password_hash(user.password_hash, password or ""):
            self.repo.add(
                models.AccountAccess(
                    user_id=user.id if user else None,
                    action="signin",
                    outcome="rejected",
                    detail=username,
                )
            )
            self.repo.commit()
            raise ValueError("Invalid username or password")
        if not user.enabled:
            self.repo.add(
                models.AccountAccess(
                    user_id=user.id, action="signin", outcome="rejected", detail="disabled"
                )
            )
            self.repo.commit()
            raise ValueError("Account is disabled; contact an administrator")
        session = self.repo.add(models.SessionRecord(user_id=user.id))
        self.repo.flush()
        self.repo.add(
            models.AccountAccess(
                user_id=user.id, action="signin", outcome="success", detail=username
            )
        )
        record_event(self.repo, user, "auth.signin", "user", user.id, username)
        return user, session.token

    def signout(self, user, token):
        session = self.repo.session_by_token(token) if token else None
        if session and session.user_id == user.id:
            self.repo.delete(session)
        self.repo.add(
            models.AccountAccess(
                user_id=user.id, action="signout", outcome="success", detail=user.username
            )
        )
        record_event(self.repo, user, "auth.signout", "user", user.id, user.username)

    def revoke_session(self, user, target_token):
        session = self.repo.session_by_token(target_token)
        if not session or session.user_id != user.id:
            raise ValueError("Unknown session token")
        self.repo.delete(session)
        self.repo.add(
            models.AccountAccess(
                user_id=user.id, action="revoke", outcome="success", detail=target_token
            )
        )
        record_event(self.repo, user, "auth.revoke", "session", session.id, target_token)
        return True

    def access_log(self, user):
        return self.repo.list_all(models.AccountAccess, user_id=user.id, order_field="id")


# ---------------------------------------------------------------------------
# DATA-02/03/04  Datasets: upload, catalog, preview
# ---------------------------------------------------------------------------

class DatasetService:
    def __init__(self, repo: Repository, config):
        self.repo = repo
        self.config = config

    def _upload_dir(self):
        directory = os.environ.get("UPLOAD_DIR", self.config.UPLOAD_DIR)
        os.makedirs(directory, exist_ok=True)
        return directory

    def _enforce_limits(self, user):
        limit = int(self.repo.setting("max_datasets_per_analyst", "50"))
        current = self.repo.count(models.Dataset, owner_id=user.id)
        if current >= limit:
            raise ValueError(
                f"Dataset limit reached ({limit}); delete datasets or ask an admin to raise the limit"
            )
        max_mb = int(self.repo.setting("max_upload_mb", str(self.config.MAX_UPLOAD_MB)))

    def upload(self, user, file_storage, name, description, tags, visibility):
        """Store file, detect schema, persist dataset + upload + catalog."""
        self._enforce_limits(user)
        if file_storage is None or not file_storage.filename:
            raise ValueError("A file is required")
        filename = os.path.basename(file_storage.filename or "")
        content = file_storage.read()
        max_bytes = int(self.repo.setting("max_upload_mb", str(self.config.MAX_UPLOAD_MB))) * 1024 * 1024
        if len(content) > max_bytes:
            raise ValueError(
                f"File exceeds the {max_bytes // (1024 * 1024)} MB upload limit"
            )
        try:
            parsed = analytics.parse_upload(filename, content)
        except ValueError as exc:
            raise ValueError(str(exc)) from exc

        upload_dir = self._upload_dir()
        stored_name = f"{uuid.uuid4().hex}_{filename}"
        storage_path = os.path.join(upload_dir, stored_name)
        with open(storage_path, "wb") as handle:
            handle.write(content)

        dataset_name = (name or "").strip() or filename.rsplit(".", 1)[0]
        dataset = self.repo.add(
            models.Dataset(
                owner_id=user.id,
                name=dataset_name,
                format=parsed["format"],
                data_json={"columns": parsed["columns"], "rows": parsed["rows"]},
                row_count=len(parsed["rows"]),
                column_count=len(parsed["columns"]),
            )
        )
        self.repo.flush()
        stored = self.repo.add(
            models.StoredFile(
                owner_id=user.id,
                filename=filename,
                storage_path=storage_path,
                kind="upload",
                file_size=len(content),
            )
        )
        self.repo.flush()
        upload_record = self.repo.add(
            models.DatasetUpload(
                owner_id=user.id,
                dataset_id=dataset.id,
                file_id=stored.id,
                filename=filename,
                format=parsed["format"],
                rows=len(parsed["rows"]),
                columns=len(parsed["columns"]),
                status="stored",
            )
        )
        self.repo.add(
            models.DatasetCatalog(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=dataset_name,
                description=(description or "").strip(),
                tags=(tags or "").strip(),
                visibility=(visibility or "private"),
            )
        )
        self.repo.flush()
        record_event(
            self.repo, user, "dataset.upload", "dataset", dataset.id, filename
        )
        record_lineage(
            self.repo, user, "import", dataset.id, source_ref=filename,
            output_ref=f"dataset:{dataset.id}", detail=dataset_name,
        )
        return upload_record

    def create_catalog_entry(self, user, dataset_id, name, description, tags, visibility):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset or dataset.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope dataset")
        if not (name or "").strip():
            raise ValueError("name is required")
        entry = self.repo.add(
            models.DatasetCatalog(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=(name or "").strip(),
                description=(description or "").strip(),
                tags=(tags or "").strip(),
                visibility=(visibility or "private"),
            )
        )
        record_event(self.repo, user, "catalog.create", "dataset_catalog", entry.id, name)
        return entry

    def update_catalog_entry(self, user, entry_id, **fields):
        entry = self.repo.get(models.DatasetCatalog, entry_id)
        if not entry or entry.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope catalog entry")
        for key in ("name", "description", "tags", "visibility"):
            if key in fields and fields[key] is not None:
                setattr(entry, key, str(fields[key]).strip())
        record_event(
            self.repo, user, "catalog.update", "dataset_catalog", entry.id, entry.name
        )
        return entry

    def visible_catalog(self, user, search="", tag=""):
        return self.repo.catalog_visible(user.id, search=search, tag=tag)

    def accessible_datasets(self, user):
        """Datasets the user may operate on: own + shared/public of others."""
        own = self.repo.list_all(models.Dataset, owner_id=user.id)
        if user.role == models.ROLE_ADMIN:
            return self.repo.list_all(models.Dataset)
        shared_entries = (
            self.repo.session.query(models.DatasetCatalog)
            .filter(
                models.DatasetCatalog.visibility.in_(["shared", "public"]),
                models.DatasetCatalog.owner_id != user.id,
            )
            .all()
        )
        shared = [
            self.repo.get(models.Dataset, entry.dataset_id)
            for entry in shared_entries
            if self.repo.get(models.Dataset, entry.dataset_id)
        ]
        seen = {d.id for d in own}
        result = list(own)
        for dataset in shared:
            if dataset.id not in seen:
                seen.add(dataset.id)
                result.append(dataset)
        return sorted(result, key=lambda d: d.id)

    def preview(self, user, dataset_id):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset:
            raise ValueError("Unknown dataset")
        if not self._can_access(user, dataset):
            raise ValueError("You do not have access to this dataset")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        summary = analytics.summarize(columns, rows)
        preview = {
            "columns": columns,
            "rows": rows[:50],
            "row_count": len(rows),
            "column_count": len(columns),
        }
        record = self.repo.add(
            models.DataPreview(
                user_id=user.id,
                dataset_id=dataset.id,
                summary_json=summary,
                preview_json=preview,
            )
        )
        record_event(self.repo, user, "preview.run", "dataset", dataset.id, dataset.name)
        return record

    def preview_result(self, record):
        return {
            "summary": record.summary_json,
            "preview": record.preview_json,
        }

    def dataset_rows(self, user, dataset_id):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset:
            raise ValueError("Unknown dataset")
        if not self._can_access(user, dataset):
            raise ValueError("You do not have access to this dataset")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        schema = analytics.detect_schema(columns, rows)
        return {
            "dataset_id": dataset.id,
            "name": dataset.name,
            "columns": columns,
            "rows": rows,
            "schema": schema,
            "row_count": len(rows),
        }

    def _can_access(self, user, dataset):
        """Analyst sees own datasets; viewers/admins see shared/public ones."""
        if dataset.owner_id == user.id or user.role == models.ROLE_ADMIN:
            return True
        entry = (
            self.repo.session.query(models.DatasetCatalog)
            .filter_by(dataset_id=dataset.id)
            .order_by(models.DatasetCatalog.id.desc())
            .first()
        )
        return entry is not None and entry.visibility in ("shared", "public")


# ---------------------------------------------------------------------------
# DATA-05  Filter builder
# ---------------------------------------------------------------------------

class FilterService:
    def __init__(self, repo: Repository, dataset_service: DatasetService):
        self.repo = repo
        self.dataset_service = dataset_service

    def create(self, user, dataset_id, name, expression):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset or not self.dataset_service._can_access(user, dataset):
            raise ValueError("Unknown or out-of-scope dataset")
        if not (name or "").strip():
            raise ValueError("name is required")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        conditions, filtered, count = analytics.apply_filter(columns, rows, expression)
        record = self.repo.add(
            models.FilterBuilder(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=(name or "").strip(),
                expression=(expression or "").strip(),
                filter_json={"conditions": conditions, "count": count},
                row_count=count,
            )
        )
        record_event(
            self.repo, user, "filter.create", "filter_builder", record.id, name
        )
        record_lineage(
            self.repo, user, "filter", dataset.id,
            source_ref=f"dataset:{dataset.id}",
            output_ref=f"filter:{record.id}",
            detail=f"{record.expression} -> {count} rows",
        )
        return record

    def update(self, user, filter_id, name=None, expression=None):
        record = self.repo.get(models.FilterBuilder, filter_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope filter")
        if name is not None:
            record.name = (name or "").strip()
        if expression is not None and (expression or "").strip() != record.expression:
            dataset = self.repo.dataset_for_upload(record.dataset_id)
            conditions, filtered, count = analytics.apply_filter(
                dataset.data_json["columns"], dataset.data_json["rows"], expression
            )
            record.expression = (expression or "").strip()
            record.filter_json = {"conditions": conditions, "count": count}
            record.row_count = count
        record_event(
            self.repo, user, "filter.update", "filter_builder", record.id, record.name
        )
        return record

    def list(self, user):
        return self.repo.list_all(models.FilterBuilder, owner_id=user.id)


# ---------------------------------------------------------------------------
# DATA-06  Chart builder
# ---------------------------------------------------------------------------

class ChartService:
    def __init__(self, repo: Repository, dataset_service: DatasetService):
        self.repo = repo
        self.dataset_service = dataset_service

    def create(self, user, dataset_id, name, chart_type, x_col, y_col, agg):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset or not self.dataset_service._can_access(user, dataset):
            raise ValueError("Unknown or out-of-scope dataset")
        if not (name or "").strip():
            raise ValueError("name is required")
        if chart_type not in ("bar", "line", "pie"):
            raise ValueError("chart_type must be bar, line, or pie")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        series = analytics.aggregate_chart(columns, rows, x_col, y_col, agg)
        config = {
            "x": x_col,
            "y": y_col,
            "agg": agg,
            "chart_type": chart_type,
            "series": series,
        }
        record = self.repo.add(
            models.ChartBuilder(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=(name or "").strip(),
                chart_type=chart_type,
                config_json=config,
            )
        )
        record_event(self.repo, user, "chart.create", "chart_builder", record.id, name)
        record_lineage(
            self.repo, user, "chart", dataset.id,
            source_ref=f"dataset:{dataset.id}",
            output_ref=f"chart:{record.id}",
            detail=f"{chart_type} {x_col} by {y_col}",
        )
        return record

    def update(self, user, chart_id, name=None, **config_fields):
        record = self.repo.get(models.ChartBuilder, chart_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope chart")
        if name is not None:
            record.name = (name or "").strip()
        for key in ("x_col", "y_col", "agg", "chart_type"):
            if config_fields.get(key):
                setattr(record, key.replace("_col", ""), config_fields[key])
        dataset = self.repo.dataset_for_upload(record.dataset_id)
        series = analytics.aggregate_chart(
            dataset.data_json["columns"],
            dataset.data_json["rows"],
            record.config_json["x"],
            record.config_json["y"],
            record.config_json["agg"],
        )
        record.config_json["series"] = series
        record_event(self.repo, user, "chart.update", "chart_builder", record.id, record.name)
        return record

    def list(self, user):
        return self.repo.list_all(models.ChartBuilder, owner_id=user.id)


# ---------------------------------------------------------------------------
# DATA-07  Calculated columns
# ---------------------------------------------------------------------------

class CalculatedService:
    def __init__(self, repo: Repository, dataset_service: DatasetService):
        self.repo = repo
        self.dataset_service = dataset_service

    def create(self, user, dataset_id, name, column_name, expression):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset or not self.dataset_service._can_access(user, dataset):
            raise ValueError("Unknown or out-of-scope dataset")
        if not (name or "").strip() or not (column_name or "").strip():
            raise ValueError("name and column_name are required")
        if not re_safe_name(column_name):
            raise ValueError("column_name must contain only letters, digits, and underscores")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        if column_name in columns:
            raise ValueError(f"Column '{column_name}' already exists")
        sample = analytics.validate_calc_expression(columns, rows, expression)
        record = self.repo.add(
            models.CalculatedColumns(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=(name or "").strip(),
                column_name=column_name,
                expression=(expression or "").strip(),
            )
        )
        record_event(
            self.repo, user, "calculated.create", "calculated_columns", record.id, name
        )
        record_lineage(
            self.repo, user, "calculated", dataset.id,
            source_ref=f"dataset:{dataset.id}",
            output_ref=f"calculated:{record.id}",
            detail=f"{column_name} = {expression}",
        )
        return record

    def update(self, user, calc_id, name=None, expression=None, column_name=None):
        record = self.repo.get(models.CalculatedColumns, calc_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope calculated column")
        if name is not None:
            record.name = (name or "").strip()
        if column_name is not None:
            record.column_name = column_name.strip()
        if expression is not None and (expression or "").strip() != record.expression:
            dataset = self.repo.dataset_for_upload(record.dataset_id)
            analytics.validate_calc_expression(
                dataset.data_json["columns"], dataset.data_json["rows"], expression
            )
            record.expression = (expression or "").strip()
        record_event(
            self.repo, user, "calculated.update", "calculated_columns", record.id,
            record.name,
        )
        return record

    def list(self, user):
        return self.repo.list_all(models.CalculatedColumns, owner_id=user.id)


def re_safe_name(value):
    import re

    return bool(re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]*", value or ""))


# ---------------------------------------------------------------------------
# DATA-08  Dashboard sharing
# ---------------------------------------------------------------------------

class SharingService:
    def __init__(self, repo: Repository, dataset_service: DatasetService):
        self.repo = repo
        self.dataset_service = dataset_service

    def create(self, user, dataset_id, chart_id, share_type, target):
        if share_type not in ("team", "link"):
            raise ValueError("share_type must be team or link")
        if share_type == "team" and not (target or "").strip():
            raise ValueError("target is required for team shares")
        dataset = None
        chart = None
        if dataset_id:
            dataset = self.repo.dataset_for_upload(dataset_id)
            if not dataset or not self.dataset_service._can_access(user, dataset):
                raise ValueError("Unknown or out-of-scope dataset")
        if chart_id:
            chart = self.repo.get(models.ChartBuilder, chart_id)
            if not chart or chart.owner_id != user.id:
                raise ValueError("Unknown or out-of-scope chart")
        record = self.repo.add(
            models.DashboardSharing(
                owner_id=user.id,
                dataset_id=dataset.id if dataset else None,
                chart_id=chart.id if chart else None,
                share_type=share_type,
                target=(target or "").strip(),
                link_token=uuid.uuid4().hex if share_type == "link" else "",
            )
        )
        record_event(
            self.repo, user, "share.create", "dashboard_sharing", record.id,
            f"{share_type}:{target or 'link'}",
        )
        record_lineage(
            self.repo, user, "share", dataset.id if dataset else None,
            source_ref=f"chart:{chart.id}" if chart else f"dataset:{dataset.id}" if dataset else "",
            output_ref=f"share:{record.id}",
            detail=f"{share_type} share to {target or 'public link'}",
        )
        return record

    def update(self, user, share_id, target=None, share_type=None):
        record = self.repo.get(models.DashboardSharing, share_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope share")
        if target is not None:
            record.target = (target or "").strip()
        if share_type is not None:
            if share_type not in ("team", "link"):
                raise ValueError("share_type must be team or link")
            record.share_type = share_type
            if share_type == "link" and not record.link_token:
                record.link_token = uuid.uuid4().hex
        record_event(self.repo, user, "share.update", "dashboard_sharing", record.id, "")
        return record

    def list(self, user):
        return self.repo.list_all(models.DashboardSharing, owner_id=user.id)

    def by_token(self, token):
        return (
            self.repo.session.query(models.DashboardSharing)
            .filter_by(link_token=token)
            .first()
        )


# ---------------------------------------------------------------------------
# DATA-09  Export
# ---------------------------------------------------------------------------

class ExportService:
    def __init__(self, repo: Repository, dataset_service: DatasetService, config):
        self.repo = repo
        self.dataset_service = dataset_service
        self.config = config

    def create(self, user, dataset_id, kind, filter_ref="", name=""):
        dataset = self.repo.dataset_for_upload(dataset_id)
        if not dataset or not self.dataset_service._can_access(user, dataset):
            raise ValueError("Unknown or out-of-scope dataset")
        if kind not in ("csv", "pdf"):
            raise ValueError("kind must be csv or pdf")
        columns = dataset.data_json["columns"]
        rows = dataset.data_json["rows"]
        conditions = []
        if (filter_ref or "").strip():
            conditions, rows, count = analytics.apply_filter(columns, rows, filter_ref)
        title = (name or "").strip() or dataset.name
        if kind == "csv":
            payload = analytics.build_csv(columns, rows)
            filename = f"{re_safe(title)}.csv"
        else:
            lines = ["Data Analytics Dashboard - Export Report", ""]
            lines.append(f"Dataset: {dataset.name}")
            lines.append(f"Rows: {len(rows)}   Columns: {len(columns)}")
            if conditions:
                lines.append(f"Filter: {filter_ref}")
            lines.append("")
            lines.append("Columns: " + ", ".join(columns))
            lines.append("")
            lines.append("Data preview (first 25 rows):")
            lines.append(" | ".join(columns))
            for row in rows[:25]:
                lines.append(" | ".join(_cell_text(v) for v in row))
            payload = analytics.build_pdf(title, lines)
            filename = f"{re_safe(title)}.pdf"
        stored_name = f"{uuid.uuid4().hex}_{filename}"
        storage_path = os.path.join(self.dataset_service._upload_dir(), stored_name)
        with open(storage_path, "wb") as handle:
            handle.write(payload)
        stored = self.repo.add(
            models.StoredFile(
                owner_id=user.id,
                filename=filename,
                storage_path=storage_path,
                kind="export",
                file_size=len(payload),
            )
        )
        self.repo.flush()
        record = self.repo.add(
            models.Export(
                owner_id=user.id,
                dataset_id=dataset.id,
                kind=kind,
                filter_ref=(filter_ref or "").strip(),
                file_id=stored.id,
                status="ready",
            )
        )
        record_event(
            self.repo, user, "export.create", "export", record.id, f"{kind} {filename}"
        )
        record_lineage(
            self.repo, user, "export", dataset.id,
            source_ref=f"dataset:{dataset.id}",
            output_ref=f"export:{record.id}",
            detail=f"{kind} export of {len(rows)} rows",
        )
        return record

    def list(self, user):
        return self.repo.list_all(models.Export, owner_id=user.id)

    def get(self, user, export_id):
        record = self.repo.get(models.Export, export_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope export")
        return record


def re_safe(text):
    import re

    return re.sub(r"[^A-Za-z0-9_\-]+", "_", text or "export")[:60]


def _cell_text(value):
    if value is None:
        return ""
    return str(value)


# ---------------------------------------------------------------------------
# DATA-10  Data source connections
# ---------------------------------------------------------------------------

class DataSourceService:
    def __init__(self, repo: Repository, dataset_service: DatasetService):
        self.repo = repo
        self.dataset_service = dataset_service

    def create(self, user, name, kind, config=None):
        if not (name or "").strip():
            raise ValueError("name is required")
        if kind not in ("mockdb", "mockapi"):
            raise ValueError("kind must be mockdb or mockapi")
        config = config or {}
        record = self.repo.add(
            models.DataSourceConnections(
                owner_id=user.id,
                name=(name or "").strip(),
                kind=kind,
                config_json=config,
                status="configured",
            )
        )
        record_event(
            self.repo, user, "source.create", "data_source_connections", record.id, name
        )
        return record

    def test(self, user, source_id):
        record = self.repo.get(models.DataSourceConnections, source_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope data source")
        try:
            data = analytics.mock_source_data(
                record.kind, record.name, record.config_json.get("seed", 1)
            )
            record.status = "connected"
            record.config_json["last_test"] = datetime.utcnow().isoformat() + "Z"
            record.config_json["sample_rows"] = len(data["rows"])
        except ValueError as exc:
            record.status = "failed"
            record.config_json["last_error"] = str(exc)
        record_event(
            self.repo, user, "source.test", "data_source_connections", record.id,
            record.status,
        )
        return record, analytics.mock_source_data(
            record.kind, record.name, record.config_json.get("seed", 1)
        )

    def import_dataset(self, user, source_id, dataset_name):
        record = self.repo.get(models.DataSourceConnections, source_id)
        if not record or record.owner_id != user.id:
            raise ValueError("Unknown or out-of-scope data source")
        self.dataset_service._enforce_limits(user)
        data = analytics.mock_source_data(
            record.kind, record.name, record.config_json.get("seed", 1)
        )
        dataset = self.repo.add(
            models.Dataset(
                owner_id=user.id,
                name=(dataset_name or "").strip() or f"{record.name} snapshot",
                format="json",
                data_json=data,
                row_count=len(data["rows"]),
                column_count=len(data["columns"]),
            )
        )
        self.repo.flush()
        self.repo.add(
            models.DatasetCatalog(
                owner_id=user.id,
                dataset_id=dataset.id,
                name=dataset.name,
                description=f"Imported from mock {record.kind} source '{record.name}'",
                tags=f"source,{record.kind}",
                visibility="private",
            )
        )
        record_event(
            self.repo, user, "source.import", "dataset", dataset.id, record.name
        )
        record_lineage(
            self.repo, user, "import", dataset.id,
            source_ref=f"source:{record.id}",
            output_ref=f"dataset:{dataset.id}",
            detail=f"imported from {record.kind} '{record.name}'",
        )
        return dataset

    def list(self, user, admin=False):
        if admin:
            return self.repo.list_all(models.DataSourceConnections)
        return self.repo.list_all(models.DataSourceConnections, owner_id=user.id)


# ---------------------------------------------------------------------------
# DATA-11  Audit and lineage
# ---------------------------------------------------------------------------

class AuditService:
    def __init__(self, repo: Repository):
        self.repo = repo

    def lineage(self, user, admin=False):
        if admin:
            return self.repo.lineage_for(user_id=None)
        return self.repo.lineage_for(user_id=user.id)

    def events(self, user, admin=False, limit=100):
        if admin:
            return self.repo.audit_events(limit=limit)
        return self.repo.audit_events(limit=limit, user_id=user.id)


# ---------------------------------------------------------------------------
# DATA-12  Admin operations
# ---------------------------------------------------------------------------

class AdminService:
    def __init__(self, repo: Repository):
        self.repo = repo

    def users(self):
        return self.repo.users_all()

    def perform(self, admin, action, target="", detail="", value=None):
        """Run a privileged operation and write an audit trail."""
        action = (action or "").strip()
        if not action:
            raise ValueError("action is required")
        target = (target or "").strip()
        result = {"action": action, "target": target, "detail": detail}

        if action in ("user.disable", "user.enable", "user.role"):
            user = self.repo.user_by_username(target)
            if not user:
                raise ValueError(f"Unknown user: {target}")
            if user.id == admin.id:
                raise ValueError("You cannot modify your own account")
            if action == "user.disable":
                user.enabled = False
                result["detail"] = f"user '{target}' disabled"
            elif action == "user.enable":
                user.enabled = True
                result["detail"] = f"user '{target}' enabled"
            else:
                role = (detail or "").strip()
                if role not in models.ROLES:
                    raise ValueError(f"Invalid role: {role}")
                user.role = role
                result["detail"] = f"user '{target}' role -> {role}"
        elif action == "retention.run":
            days = int(target or self.repo.setting("retention_days", "30"))
            deleted_sessions = self.repo.expire_sessions_older_than(days)
            deleted_events = self.repo.trim_audit_events_older_than(days)
            self.repo.set_setting("retention_days", str(days))
            result["detail"] = (
                f"retention run over {days} days: {deleted_sessions} sessions, "
                f"{deleted_events} audit events removed"
            )
        elif action in ("limit.update", "setting.update"):
            if not target:
                raise ValueError("target setting key is required")
            if value is None:
                raise ValueError("value is required")
            self.repo.set_setting(target, str(value))
            result["detail"] = f"setting '{target}' -> {value}"
        else:
            raise ValueError(f"Unsupported admin action: {action}")

        self.repo.flush()
        record = self.repo.add(
            models.AdminOperations(
                admin_id=admin.id,
                action=action,
                target=target,
                detail=result["detail"],
            )
        )
        record_event(
            self.repo, admin, "admin." + action, "admin_operations", record.id,
            result["detail"],
        )
        return record

    def operations(self):
        return self.repo.list_all(models.AdminOperations, order_field="id")

    def get_user(self, username):
        return self.repo.user_by_username(username)
