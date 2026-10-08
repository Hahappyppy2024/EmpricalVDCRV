"""Persistent entities for the P12 Data Analytics Dashboard.

Each use case in the specification lists its persistent entities; the tables
below implement every one of them:

  DATA-01  Account access      -> users, sessions, account_access
  DATA-02  Dataset upload      -> users, sessions, dataset_uploads, stored_files, datasets
  DATA-03  Dataset catalog     -> users, sessions, dataset_catalog
  DATA-04  Data preview        -> users, sessions, data_preview
  DATA-05  Filter builder      -> users, sessions, filter_builder
  DATA-06  Chart builder       -> users, sessions, chart_builder
  DATA-07  Calculated columns  -> users, sessions, calculated_columns
  DATA-08  Dashboard sharing   -> users, sessions, dashboard_sharing
  DATA-09  Export              -> users, sessions, exports, stored_files
  DATA-10  Data source conns   -> users, sessions, data_source_connections
  DATA-11  Audit and lineage   -> users, sessions, audit_and_lineage, audit_events
  DATA-12  Admin operations    -> users, sessions, admin_operations, audit_events
"""
import uuid
from datetime import datetime, timedelta

from sqlalchemy import (
    JSON,
    Boolean,
    Column,
    DateTime,
    ForeignKey,
    Integer,
    String,
    Text,
)

from .extensions import Base

# Roles supported by the application. Seeded and enforced by auth helpers.
ROLE_ADMIN = "admin"
ROLE_ANALYST = "analyst"
ROLE_VIEWER = "viewer"
ROLES = (ROLE_ADMIN, ROLE_ANALYST, ROLE_VIEWER)


def _now() -> datetime:
    return datetime.utcnow()


def _token() -> str:
    return uuid.uuid4().hex


class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True)
    username = Column(String(64), unique=True, nullable=False)
    email = Column(String(255), unique=True, nullable=False)
    password_hash = Column(String(255), nullable=False)
    role = Column(String(32), nullable=False, default=ROLE_VIEWER)
    enabled = Column(Boolean, nullable=False, default=True)
    created_at = Column(DateTime, nullable=False, default=_now)


class SessionRecord(Base):
    """Server-side sessions. The HTTP-only cookie stores only the token."""

    __tablename__ = "sessions"

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    token = Column(String(64), unique=True, nullable=False, default=_token)
    created_at = Column(DateTime, nullable=False, default=_now)
    expires_at = Column(
        DateTime,
        nullable=False,
        default=lambda: _now() + timedelta(days=7),
    )


class AccountAccess(Base):
    """DATA-01: sign-in / sign-up / sign-out / session-revocation audit."""

    __tablename__ = "account_access"

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=True)
    action = Column(String(32), nullable=False)  # signin | signup | signout | revoke
    outcome = Column(String(32), nullable=False)  # success | rejected
    detail = Column(Text, nullable=False, default="")
    created_at = Column(DateTime, nullable=False, default=_now)


class Dataset(Base):
    """Internal core entity: parsed rows of an uploaded dataset."""

    __tablename__ = "datasets"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    name = Column(String(255), nullable=False)
    format = Column(String(16), nullable=False)  # csv | json
    data_json = Column(JSON, nullable=False)  # {"columns": [...], "rows": [[...]]}
    row_count = Column(Integer, nullable=False, default=0)
    column_count = Column(Integer, nullable=False, default=0)
    created_at = Column(DateTime, nullable=False, default=_now)


class StoredFile(Base):
    """Physical file stored on the local adapter (uploads and exports)."""

    __tablename__ = "stored_files"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    filename = Column(String(255), nullable=False)
    storage_path = Column(String(512), nullable=False)
    kind = Column(String(32), nullable=False)  # upload | export
    file_size = Column(Integer, nullable=False, default=0)
    created_at = Column(DateTime, nullable=False, default=_now)


class DatasetUpload(Base):
    """DATA-02: metadata of a stored dataset upload."""

    __tablename__ = "dataset_uploads"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    file_id = Column(Integer, ForeignKey("stored_files.id"), nullable=False)
    filename = Column(String(255), nullable=False)
    format = Column(String(16), nullable=False)
    rows = Column(Integer, nullable=False, default=0)
    columns = Column(Integer, nullable=False, default=0)
    status = Column(String(32), nullable=False, default="stored")
    created_at = Column(DateTime, nullable=False, default=_now)


class DatasetCatalog(Base):
    """DATA-03: searchable, taggable catalog entry describing a dataset."""

    __tablename__ = "dataset_catalog"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    name = Column(String(255), nullable=False)
    description = Column(Text, nullable=False, default="")
    tags = Column(Text, nullable=False, default="")  # comma separated
    visibility = Column(String(16), nullable=False, default="private")  # private|shared|public
    created_at = Column(DateTime, nullable=False, default=_now)


class DataPreview(Base):
    """DATA-04: a snapshot of a data preview (summary + sample rows)."""

    __tablename__ = "data_preview"

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    summary_json = Column(JSON, nullable=False, default=dict)
    preview_json = Column(JSON, nullable=False, default=dict)
    created_at = Column(DateTime, nullable=False, default=_now)


class FilterBuilder(Base):
    """DATA-05: a saved filter / saved view over a dataset."""

    __tablename__ = "filter_builder"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    name = Column(String(255), nullable=False)
    expression = Column(Text, nullable=False)
    filter_json = Column(JSON, nullable=False, default=dict)
    row_count = Column(Integer, nullable=False, default=0)
    created_at = Column(DateTime, nullable=False, default=_now)


class ChartBuilder(Base):
    """DATA-06: a saved chart configuration over a dataset."""

    __tablename__ = "chart_builder"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    name = Column(String(255), nullable=False)
    chart_type = Column(String(32), nullable=False)  # bar | line | pie
    config_json = Column(JSON, nullable=False, default=dict)  # x, y, agg, filter
    created_at = Column(DateTime, nullable=False, default=_now)


class CalculatedColumns(Base):
    """DATA-07: a calculated field defined by an approved expression."""

    __tablename__ = "calculated_columns"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    name = Column(String(255), nullable=False)
    column_name = Column(String(64), nullable=False)
    expression = Column(Text, nullable=False)
    created_at = Column(DateTime, nullable=False, default=_now)


class DashboardSharing(Base):
    """DATA-08: sharing a dashboard (dataset and/or chart) with a team or link."""

    __tablename__ = "dashboard_sharing"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=True)
    chart_id = Column(Integer, ForeignKey("chart_builder.id"), nullable=True)
    share_type = Column(String(16), nullable=False)  # team | link
    target = Column(String(255), nullable=False, default="")
    link_token = Column(String(64), nullable=False, default=_token)
    created_at = Column(DateTime, nullable=False, default=_now)


class Export(Base):
    """DATA-09: a generated export (CSV or PDF) plus its stored file."""

    __tablename__ = "exports"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, ForeignKey("datasets.id"), nullable=False)
    kind = Column(String(32), nullable=False)  # csv | pdf
    filter_ref = Column(String(255), nullable=False, default="")
    file_id = Column(Integer, ForeignKey("stored_files.id"), nullable=True)
    status = Column(String(32), nullable=False, default="ready")
    created_at = Column(DateTime, nullable=False, default=_now)


class DataSourceConnections(Base):
    """DATA-10: configured mock database / mock API data source."""

    __tablename__ = "data_source_connections"

    id = Column(Integer, primary_key=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    name = Column(String(255), nullable=False)
    kind = Column(String(16), nullable=False)  # mockdb | mockapi
    config_json = Column(JSON, nullable=False, default=dict)
    status = Column(String(32), nullable=False, default="configured")  # configured|connected|failed
    created_at = Column(DateTime, nullable=False, default=_now)


class AuditAndLineage(Base):
    """DATA-11: lineage chain of imports, transformations, and exports."""

    __tablename__ = "audit_and_lineage"

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    dataset_id = Column(Integer, nullable=True)
    operation = Column(String(32), nullable=False)  # import | filter | chart | calculated | export | share | source
    source_ref = Column(String(255), nullable=False, default="")
    output_ref = Column(String(255), nullable=False, default="")
    detail = Column(Text, nullable=False, default="")
    created_at = Column(DateTime, nullable=False, default=_now)


class AuditEvent(Base):
    """DATA-11/12: every significant user action, including privileged ops."""

    __tablename__ = "audit_events"

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    action = Column(String(64), nullable=False)
    entity_type = Column(String(64), nullable=False, default="")
    entity_id = Column(Integer, nullable=True)
    detail = Column(Text, nullable=False, default="")
    created_at = Column(DateTime, nullable=False, default=_now)


class AdminOperations(Base):
    """DATA-12: log of privileged management operations."""

    __tablename__ = "admin_operations"

    id = Column(Integer, primary_key=True)
    admin_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    action = Column(String(64), nullable=False)
    target = Column(String(255), nullable=False, default="")
    detail = Column(Text, nullable=False, default="")
    created_at = Column(DateTime, nullable=False, default=_now)


class Setting(Base):
    """Key/value settings (retention, dataset limits) editable by admins."""

    __tablename__ = "settings"

    id = Column(Integer, primary_key=True)
    key = Column(String(64), unique=True, nullable=False)
    value = Column(String(255), nullable=False, default="")


def serialize(obj) -> dict:
    """Deterministic dict serialization of any model instance."""
    if obj is None:
        return None
    data = {}
    for col in obj.__table__.columns:
        value = getattr(obj, col.name)
        if isinstance(value, datetime):
            value = value.isoformat() + "Z"
        data[col.name] = value
    data.pop("password_hash", None)
    return data
