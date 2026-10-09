from __future__ import annotations

from typing import Optional

from sqlalchemy import select

from ..db import SessionLocal
from ..models import Channel, Workspace


class WorkspaceRepository:
    @staticmethod
    def by_id(workspace_id: int) -> Optional[Workspace]:
        with SessionLocal() as session:
            return session.get(Workspace, workspace_id)

    @staticmethod
    def by_name(name: str) -> Optional[Workspace]:
        with SessionLocal() as session:
            return session.execute(
                select(Workspace).where(Workspace.name == name.strip())
            ).scalar_one_or_none()

    @staticmethod
    def list_all() -> list[Workspace]:
        with SessionLocal() as session:
            return list(
                session.execute(select(Workspace).order_by(Workspace.name)).scalars()
            )

    @staticmethod
    def list_for_user(user_id: int) -> list[Workspace]:
        from ..models import Membership

        with SessionLocal() as session:
            ws_ids = session.execute(
                select(Membership.workspace_id).where(
                    Membership.user_id == user_id,
                    Membership.status == "active",
                )
            ).scalars()
            return list(
                session.execute(
                    select(Workspace)
                    .where(Workspace.id.in_(list(ws_ids)))
                    .order_by(Workspace.name)
                ).scalars()
            )

    @staticmethod
    def create(name: str, description: str, owner_id: int) -> Workspace:
        with SessionLocal() as session:
            ws = Workspace(
                name=name.strip(),
                description=description.strip(),
                owner_id=owner_id,
            )
            session.add(ws)
            session.commit()
            session.refresh(ws)
            session.expunge(ws)
            return ws

    @staticmethod
    def update(workspace_id: int, description: Optional[str] = None) -> Optional[Workspace]:
        with SessionLocal() as session:
            ws = session.get(Workspace, workspace_id)
            if ws is None:
                return None
            if description is not None:
                ws.description = description.strip()
            session.commit()
            session.refresh(ws)
            session.expunge(ws)
            return ws


class ChannelRepository:
    @staticmethod
    def by_id(channel_id: int) -> Optional[Channel]:
        with SessionLocal() as session:
            return session.get(Channel, channel_id)

    @staticmethod
    def by_name_in_workspace(workspace_id: int, name: str) -> Optional[Channel]:
        with SessionLocal() as session:
            return session.execute(
                select(Channel).where(
                    Channel.workspace_id == workspace_id, Channel.name == name.strip()
                )
            ).scalar_one_or_none()

    @staticmethod
    def list_in_workspace(workspace_id: int, include_private: bool) -> list[Channel]:
        with SessionLocal() as session:
            stmt = select(Channel).where(Channel.workspace_id == workspace_id)
            if not include_private:
                stmt = stmt.where(Channel.is_private.is_(False))
            stmt = stmt.order_by(Channel.name)
            return list(session.execute(stmt).scalars())

    @staticmethod
    def create(
        workspace_id: int,
        name: str,
        description: str,
        is_private: bool,
        topic: str,
    ) -> Channel:
        with SessionLocal() as session:
            channel = Channel(
                workspace_id=workspace_id,
                name=name.strip(),
                description=description.strip(),
                is_private=is_private,
                topic=topic.strip() or "general",
            )
            session.add(channel)
            session.commit()
            session.refresh(channel)
            session.expunge(channel)
            return channel

    @staticmethod
    def update(
        channel_id: int,
        description: Optional[str] = None,
        topic: Optional[str] = None,
        archived: Optional[bool] = None,
    ) -> Optional[Channel]:
        with SessionLocal() as session:
            channel = session.get(Channel, channel_id)
            if channel is None:
                return None
            if description is not None:
                channel.description = description.strip()
            if topic is not None:
                channel.topic = topic.strip() or "general"
            if archived is not None:
                channel.archived = archived
            session.commit()
            session.refresh(channel)
            session.expunge(channel)
            return channel
