"""Repository / data-access layer.

Thin, explicit persistence helpers built on SQLAlchemy 2. Every business
service talks to the database through this layer only.
"""
from datetime import datetime

from sqlalchemy import or_

from . import models


class Repository:
    """Generic CRUD + a few domain-specific lookups over a DB session."""

    def __init__(self, session):
        self.session = session

    # -- transaction helpers ------------------------------------------------
    def add(self, obj):
        self.session.add(obj)
        return obj

    def flush(self):
        self.session.flush()

    def commit(self):
        self.session.commit()

    def rollback(self):
        self.session.rollback()

    def delete(self, obj):
        self.session.delete(obj)

    # -- generic queries ----------------------------------------------------
    def get(self, model, ident):
        return self.session.get(model, ident)

    def list_all(self, model, order_field="id", order="desc", **filters):
        query = self.session.query(model)
        for key, value in filters.items():
            query = query.filter(getattr(model, key) == value)
        column = getattr(model, order_field)
        query = query.order_by(column.desc() if order == "desc" else column.asc())
        return query.all()

    def count(self, model, **filters):
        query = self.session.query(model)
        for key, value in filters.items():
            query = query.filter(getattr(model, key) == value)
        return query.count()

    # -- domain-specific lookups ---------------------------------------------
    def user_by_username(self, username):
        return self.session.query(models.User).filter_by(username=username).first()

    def user_by_email(self, email):
        return self.session.query(models.User).filter_by(email=email).first()

    def session_by_token(self, token):
        return self.session.query(models.SessionRecord).filter_by(token=token).first()

    def sessions_for_user(self, user_id):
        return (
            self.session.query(models.SessionRecord)
            .filter_by(user_id=user_id)
            .order_by(models.SessionRecord.id.desc())
            .all()
        )

    def dataset_for_upload(self, dataset_id):
        return self.session.get(models.Dataset, dataset_id)

    def catalog_visible(self, user_id, search="", tag=""):
        """Entries the user may see: own + shared + public of others."""
        query = self.session.query(models.DatasetCatalog).join(models.Dataset).filter(
            or_(
                models.DatasetCatalog.owner_id == user_id,
                models.DatasetCatalog.visibility.in_(["shared", "public"]),
            )
        )
        if search:
            like = f"%{search}%"
            query = query.filter(
                or_(
                    models.DatasetCatalog.name.ilike(like),
                    models.DatasetCatalog.description.ilike(like),
                    models.DatasetCatalog.tags.ilike(like),
                )
            )
        if tag:
            query = query.filter(models.DatasetCatalog.tags.ilike(f"%{tag}%"))
        return query.order_by(models.DatasetCatalog.id.desc()).all()

    def users_all(self):
        return self.session.query(models.User).order_by(models.User.id.asc()).all()

    def users_with_role(self, role):
        return self.session.query(models.User).filter_by(role=role).all()

    def setting(self, key, default=""):
        row = self.session.query(models.Setting).filter_by(key=key).first()
        return row.value if row else default

    def set_setting(self, key, value):
        row = self.session.query(models.Setting).filter_by(key=key).first()
        if row:
            row.value = value
        else:
            self.session.add(models.Setting(key=key, value=value))
        return value

    def audit_events(self, limit=100, user_id=None):
        query = self.session.query(models.AuditEvent)
        if user_id is not None:
            query = query.filter(models.AuditEvent.user_id == user_id)
        return query.order_by(models.AuditEvent.id.desc()).limit(limit).all()

    def lineage_for(self, user_id=None, limit=100):
        query = self.session.query(models.AuditAndLineage)
        if user_id is not None:
            query = query.filter(models.AuditAndLineage.user_id == user_id)
        return query.order_by(models.AuditAndLineage.id.desc()).limit(limit).all()

    def expire_sessions_older_than(self, days):
        cutoff = datetime.utcnow().replace(
            hour=0, minute=0, second=0, microsecond=0
        )
        from datetime import timedelta

        cutoff = datetime.utcnow() - timedelta(days=days)
        deleted = (
            self.session.query(models.SessionRecord)
            .filter(models.SessionRecord.created_at < cutoff)
            .delete(synchronize_session=False)
        )
        return deleted

    def trim_audit_events_older_than(self, days):
        from datetime import timedelta

        cutoff = datetime.utcnow() - timedelta(days=days)
        deleted = (
            self.session.query(models.AuditEvent)
            .filter(models.AuditEvent.created_at < cutoff)
            .delete(synchronize_session=False)
        )
        return deleted

    def analytics_counts(self, user_id):
        return {
            "datasets": self.count(models.Dataset, owner_id=user_id),
            "uploads": self.count(models.DatasetUpload, owner_id=user_id),
            "filters": self.count(models.FilterBuilder, owner_id=user_id),
            "charts": self.count(models.ChartBuilder, owner_id=user_id),
            "calculated": self.count(models.CalculatedColumns, owner_id=user_id),
            "shares": self.count(models.DashboardSharing, owner_id=user_id),
            "exports": self.count(models.Export, owner_id=user_id),
            "sources": self.count(models.DataSourceConnections, owner_id=user_id),
        }
