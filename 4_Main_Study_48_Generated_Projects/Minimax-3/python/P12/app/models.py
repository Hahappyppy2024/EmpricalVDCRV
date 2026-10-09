"""SQLAlchemy ORM models for the Data Analytics Dashboard."""
from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from sqlalchemy import (
    Boolean,
    DateTime,
    Float,
    ForeignKey,
    Integer,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from .extensions import db


def utcnow() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None)


class User(db.Model):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    username: Mapped[str] = mapped_column(String(64), unique=True, nullable=False)
    email: Mapped[str] = mapped_column(String(255), unique=True, nullable=False)
    display_name: Mapped[str] = mapped_column(String(128), nullable=False)
    password_hash: Mapped[str] = mapped_column(String(255), nullable=False)
    role: Mapped[str] = mapped_column(String(16), nullable=False, default="viewer")
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    last_login_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)

    sessions: Mapped[list["Session"]] = relationship(
        back_populates="user", cascade="all, delete-orphan"
    )
    datasets: Mapped[list["Dataset"]] = relationship(
        back_populates="owner", cascade="all, delete-orphan"
    )

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "username": self.username,
            "email": self.email,
            "display_name": self.display_name,
            "role": self.role,
            "is_active": self.is_active,
            "created_at": self.created_at.isoformat() + "Z",
            "last_login_at": self.last_login_at.isoformat() + "Z"
            if self.last_login_at
            else None,
        }


class Session(db.Model):
    __tablename__ = "sessions"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    token: Mapped[str] = mapped_column(String(96), unique=True, nullable=False)
    user_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    issued_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    expires_at: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    ip_address: Mapped[str | None] = mapped_column(String(64), nullable=True)
    user_agent: Mapped[str | None] = mapped_column(String(255), nullable=True)
    revoked: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)

    user: Mapped[User] = relationship(back_populates="sessions")

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "token": self.token,
            "user_id": self.user_id,
            "issued_at": self.issued_at.isoformat() + "Z",
            "expires_at": self.expires_at.isoformat() + "Z",
            "revoked": self.revoked,
        }


class Dataset(db.Model):
    __tablename__ = "datasets"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    name: Mapped[str] = mapped_column(String(128), nullable=False)
    description: Mapped[str] = mapped_column(Text, default="", nullable=False)
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    visibility: Mapped[str] = mapped_column(String(16), default="private", nullable=False)
    source_format: Mapped[str] = mapped_column(String(16), default="csv", nullable=False)
    file_path: Mapped[str] = mapped_column(String(512), nullable=False)
    row_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    column_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    schema_json: Mapped[str] = mapped_column(Text, default="[]", nullable=False)
    tags_csv: Mapped[str] = mapped_column(String(512), default="", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    owner: Mapped[User] = relationship(back_populates="datasets")
    shares: Mapped[list["DashboardShare"]] = relationship(
        back_populates="dataset", cascade="all, delete-orphan"
    )
    filters: Mapped[list["SavedFilterView"]] = relationship(
        back_populates="dataset", cascade="all, delete-orphan"
    )
    charts: Mapped[list["ChartSpec"]] = relationship(
        back_populates="dataset", cascade="all, delete-orphan"
    )
    calculated_columns: Mapped[list["CalculatedColumn"]] = relationship(
        back_populates="dataset", cascade="all, delete-orphan"
    )
    exports: Mapped[list["ExportRecord"]] = relationship(
        back_populates="dataset", cascade="all, delete-orphan"
    )

    def to_summary(self) -> dict[str, Any]:
        import json

        return {
            "id": self.id,
            "name": self.name,
            "description": self.description,
            "owner_id": self.owner_id,
            "owner_username": self.owner.username if self.owner else None,
            "visibility": self.visibility,
            "source_format": self.source_format,
            "row_count": self.row_count,
            "column_count": self.column_count,
            "tags": [t for t in self.tags_csv.split(",") if t],
            "created_at": self.created_at.isoformat() + "Z",
            "updated_at": self.updated_at.isoformat() + "Z",
            "schema": json.loads(self.schema_json),
        }


class DataSource(db.Model):
    __tablename__ = "data_sources"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    name: Mapped[str] = mapped_column(String(128), nullable=False, unique=True)
    kind: Mapped[str] = mapped_column(String(32), nullable=False)  # csv, json, api_mock, db_mock
    config_json: Mapped[str] = mapped_column(Text, default="{}", nullable=False)
    description: Mapped[str] = mapped_column(Text, default="", nullable=False)
    enabled: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    created_by: Mapped[int | None] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    def to_dict(self) -> dict[str, Any]:
        import json

        return {
            "id": self.id,
            "name": self.name,
            "kind": self.kind,
            "description": self.description,
            "enabled": self.enabled,
            "created_by": self.created_by,
            "created_at": self.created_at.isoformat() + "Z",
            "updated_at": self.updated_at.isoformat() + "Z",
            "config": json.loads(self.config_json),
        }


class SavedFilterView(db.Model):
    __tablename__ = "saved_filter_views"
    __table_args__ = (
        UniqueConstraint("dataset_id", "name", name="uq_filter_dataset_name"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    dataset_id: Mapped[int] = mapped_column(
        ForeignKey("datasets.id", ondelete="CASCADE"), nullable=False
    )
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    name: Mapped[str] = mapped_column(String(128), nullable=False)
    expression: Mapped[str] = mapped_column(Text, nullable=False)
    description: Mapped[str] = mapped_column(Text, default="", nullable=False)
    is_public: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    dataset: Mapped[Dataset] = relationship(back_populates="filters")

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "dataset_id": self.dataset_id,
            "owner_id": self.owner_id,
            "name": self.name,
            "expression": self.expression,
            "description": self.description,
            "is_public": self.is_public,
            "created_at": self.created_at.isoformat() + "Z",
            "updated_at": self.updated_at.isoformat() + "Z",
        }


class ChartSpec(db.Model):
    __tablename__ = "chart_specs"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    dataset_id: Mapped[int] = mapped_column(
        ForeignKey("datasets.id", ondelete="CASCADE"), nullable=False
    )
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    name: Mapped[str] = mapped_column(String(128), nullable=False)
    chart_type: Mapped[str] = mapped_column(String(32), nullable=False)  # bar, line, pie, scatter
    config_json: Mapped[str] = mapped_column(Text, default="{}", nullable=False)
    filter_expression: Mapped[str] = mapped_column(Text, default="", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    dataset: Mapped[Dataset] = relationship(back_populates="charts")

    def to_dict(self) -> dict[str, Any]:
        import json

        return {
            "id": self.id,
            "dataset_id": self.dataset_id,
            "owner_id": self.owner_id,
            "name": self.name,
            "chart_type": self.chart_type,
            "config": json.loads(self.config_json),
            "filter_expression": self.filter_expression,
            "created_at": self.created_at.isoformat() + "Z",
            "updated_at": self.updated_at.isoformat() + "Z",
        }


class CalculatedColumn(db.Model):
    __tablename__ = "calculated_columns"
    __table_args__ = (
        UniqueConstraint("dataset_id", "name", name="uq_calc_dataset_name"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    dataset_id: Mapped[int] = mapped_column(
        ForeignKey("datasets.id", ondelete="CASCADE"), nullable=False
    )
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    name: Mapped[str] = mapped_column(String(64), nullable=False)
    expression: Mapped[str] = mapped_column(Text, nullable=False)
    description: Mapped[str] = mapped_column(Text, default="", nullable=False)
    return_type: Mapped[str] = mapped_column(String(16), default="number", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    dataset: Mapped[Dataset] = relationship(back_populates="calculated_columns")

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "dataset_id": self.dataset_id,
            "owner_id": self.owner_id,
            "name": self.name,
            "expression": self.expression,
            "description": self.description,
            "return_type": self.return_type,
            "created_at": self.created_at.isoformat() + "Z",
            "updated_at": self.updated_at.isoformat() + "Z",
        }


class DashboardShare(db.Model):
    __tablename__ = "dashboard_shares"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    dataset_id: Mapped[int] = mapped_column(
        ForeignKey("datasets.id", ondelete="CASCADE"), nullable=False
    )
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    link_token: Mapped[str] = mapped_column(String(96), unique=True, nullable=False)
    audience: Mapped[str] = mapped_column(String(32), default="team", nullable=False)
    permission: Mapped[str] = mapped_column(String(16), default="view", nullable=False)
    description: Mapped[str] = mapped_column(Text, default="", nullable=False)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    revoked: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)

    dataset: Mapped[Dataset] = relationship(back_populates="shares")

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "dataset_id": self.dataset_id,
            "owner_id": self.owner_id,
            "link_token": self.link_token,
            "audience": self.audience,
            "permission": self.permission,
            "description": self.description,
            "expires_at": self.expires_at.isoformat() + "Z"
            if self.expires_at
            else None,
            "revoked": self.revoked,
            "created_at": self.created_at.isoformat() + "Z",
        }


class ExportRecord(db.Model):
    __tablename__ = "export_records"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    dataset_id: Mapped[int] = mapped_column(
        ForeignKey("datasets.id", ondelete="CASCADE"), nullable=False
    )
    owner_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    export_format: Mapped[str] = mapped_column(String(16), nullable=False)  # csv, pdf, json
    file_path: Mapped[str] = mapped_column(String(512), nullable=False)
    row_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    filter_expression: Mapped[str] = mapped_column(Text, default="", nullable=False)
    status: Mapped[str] = mapped_column(String(16), default="ready", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)

    dataset: Mapped[Dataset] = relationship(back_populates="exports")

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "dataset_id": self.dataset_id,
            "owner_id": self.owner_id,
            "export_format": self.export_format,
            "file_path": self.file_path,
            "row_count": self.row_count,
            "filter_expression": self.filter_expression,
            "status": self.status,
            "created_at": self.created_at.isoformat() + "Z",
        }


class AuditEvent(db.Model):
    __tablename__ = "audit_events"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    actor_id: Mapped[int | None] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    actor_username: Mapped[str] = mapped_column(String(64), nullable=False, default="system")
    action: Mapped[str] = mapped_column(String(64), nullable=False)
    entity_type: Mapped[str] = mapped_column(String(64), nullable=False)
    entity_id: Mapped[str] = mapped_column(String(64), nullable=False)
    summary: Mapped[str] = mapped_column(Text, default="", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "actor_id": self.actor_id,
            "actor_username": self.actor_username,
            "action": self.action,
            "entity_type": self.entity_type,
            "entity_id": self.entity_id,
            "summary": self.summary,
            "created_at": self.created_at.isoformat() + "Z",
        }


class AdminSettings(db.Model):
    __tablename__ = "admin_settings"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    key: Mapped[str] = mapped_column(String(64), unique=True, nullable=False)
    value: Mapped[str] = mapped_column(Text, default="", nullable=False)
    updated_by: Mapped[int | None] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=utcnow, onupdate=utcnow, nullable=False
    )

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "key": self.key,
            "value": self.value,
            "updated_by": self.updated_by,
            "updated_at": self.updated_at.isoformat() + "Z",
        }


class LineageRecord(db.Model):
    __tablename__ = "lineage_records"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    parent_type: Mapped[str] = mapped_column(String(64), nullable=False)
    parent_id: Mapped[str] = mapped_column(String(64), nullable=False)
    child_type: Mapped[str] = mapped_column(String(64), nullable=False)
    child_id: Mapped[str] = mapped_column(String(64), nullable=False)
    transformation: Mapped[str] = mapped_column(String(128), default="", nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, nullable=False)

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.id,
            "parent_type": self.parent_type,
            "parent_id": self.parent_id,
            "child_type": self.child_type,
            "child_id": self.child_id,
            "transformation": self.transformation,
            "created_at": self.created_at.isoformat() + "Z",
        }


__all__ = [
    "User",
    "Session",
    "Dataset",
    "DataSource",
    "SavedFilterView",
    "ChartSpec",
    "CalculatedColumn",
    "DashboardShare",
    "ExportRecord",
    "AuditEvent",
    "AdminSettings",
    "LineageRecord",
    "utcnow",
]
