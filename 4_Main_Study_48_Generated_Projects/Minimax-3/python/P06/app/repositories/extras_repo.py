from __future__ import annotations

from typing import Optional

from sqlalchemy import desc, select

from ..db import SessionLocal
from ..models import (
    AuditEvent,
    ConnectionState,
    ErrorLog,
    FrontendState,
    LinkPreview,
)


class LinkPreviewRepository:
    @staticmethod
    def by_url(url: str) -> Optional[LinkPreview]:
        with SessionLocal() as session:
            return session.execute(
                select(LinkPreview)
                .where(LinkPreview.url == url.strip())
                .order_by(desc(LinkPreview.created_at))
            ).scalar_one_or_none()

    @staticmethod
    def create(
        url: str,
        title: str,
        description: str,
        image_url: Optional[str],
        site_name: Optional[str],
        requested_by: int,
    ) -> LinkPreview:
        with SessionLocal() as session:
            preview = LinkPreview(
                url=url.strip(),
                title=title,
                description=description,
                image_url=image_url,
                site_name=site_name,
                requested_by=requested_by,
            )
            session.add(preview)
            session.commit()
            session.refresh(preview)
            session.expunge(preview)
            return preview

    @staticmethod
    def list_for_user(user_id: int) -> list[LinkPreview]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(LinkPreview)
                    .where(LinkPreview.requested_by == user_id)
                    .order_by(desc(LinkPreview.created_at))
                ).scalars()
            )


class AuditRepository:
    @staticmethod
    def create(
        actor_id: int,
        action: str,
        detail: str = "",
        workspace_id: Optional[int] = None,
        channel_id: Optional[int] = None,
    ) -> AuditEvent:
        with SessionLocal() as session:
            event = AuditEvent(
                actor_id=actor_id,
                action=action,
                detail=detail,
                workspace_id=workspace_id,
                channel_id=channel_id,
            )
            session.add(event)
            session.commit()
            session.refresh(event)
            session.expunge(event)
            return event

    @staticmethod
    def list_for_workspace(workspace_id: int) -> list[AuditEvent]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(AuditEvent)
                    .where(AuditEvent.workspace_id == workspace_id)
                    .order_by(desc(AuditEvent.created_at))
                ).scalars()
            )


class ErrorLogRepository:
    @staticmethod
    def create(
        code: str,
        message: str,
        user_id: Optional[int],
        source: str = "client",
    ) -> ErrorLog:
        with SessionLocal() as session:
            entry = ErrorLog(
                code=code,
                message=message,
                user_id=user_id,
                source=source,
            )
            session.add(entry)
            session.commit()
            session.refresh(entry)
            session.expunge(entry)
            return entry

    @staticmethod
    def list_all(limit: int = 100) -> list[ErrorLog]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(ErrorLog).order_by(desc(ErrorLog.created_at)).limit(limit)
                ).scalars()
            )


class FrontendStateRepository:
    @staticmethod
    def upsert(user_id: int, context: str, status: str, payload: str = "{}") -> FrontendState:
        with SessionLocal() as session:
            record = session.execute(
                select(FrontendState).where(
                    FrontendState.user_id == user_id,
                    FrontendState.context == context,
                )
            ).scalar_one_or_none()
            if record is None:
                record = FrontendState(
                    user_id=user_id, context=context, status=status, payload=payload
                )
                session.add(record)
            else:
                record.status = status
                record.payload = payload
                from datetime import datetime, timezone

                record.updated_at = datetime.now(timezone.utc)
            session.commit()
            session.refresh(record)
            session.expunge(record)
            return record

    @staticmethod
    def list_for_user(user_id: int) -> list[FrontendState]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(FrontendState).where(FrontendState.user_id == user_id)
                ).scalars()
            )


class ConnectionStateRepository:
    @staticmethod
    def open(user_id: int, connection_id: str) -> ConnectionState:
        with SessionLocal() as session:
            record = ConnectionState(
                user_id=user_id,
                connection_id=connection_id,
                status="connected",
            )
            session.add(record)
            session.commit()
            session.refresh(record)
            session.expunge(record)
            return record

    @staticmethod
    def close(connection_id: str) -> Optional[ConnectionState]:
        from datetime import datetime, timezone

        with SessionLocal() as session:
            record = session.execute(
                select(ConnectionState).where(
                    ConnectionState.connection_id == connection_id
                )
            ).scalar_one_or_none()
            if record is None:
                return None
            record.status = "disconnected"
            record.closed_at = datetime.now(timezone.utc)
            session.commit()
            session.refresh(record)
            session.expunge(record)
            return record

    @staticmethod
    def update_event_id(connection_id: str, event_id: str) -> Optional[ConnectionState]:
        with SessionLocal() as session:
            record = session.execute(
                select(ConnectionState).where(
                    ConnectionState.connection_id == connection_id
                )
            ).scalar_one_or_none()
            if record is None:
                return None
            record.last_event_id = event_id
            session.commit()
            session.refresh(record)
            session.expunge(record)
            return record

    @staticmethod
    def recent_for_user(user_id: int, limit: int = 20) -> list[ConnectionState]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(ConnectionState)
                    .where(ConnectionState.user_id == user_id)
                    .order_by(desc(ConnectionState.established_at))
                    .limit(limit)
                ).scalars()
            )
