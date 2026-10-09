from __future__ import annotations

from ..errors import ConflictError, ForbiddenError, NotFoundError, ValidationError
from ..repositories import (
    AuditRepository,
    ChannelRepository,
    MembershipRepository,
)
from .session_service import require_user


def channel_settings(channel_id: int) -> dict:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None:
        raise NotFoundError("Channel not found.")
    _ensure_owner_or_admin(channel.workspace_id, user.id)
    return _serialize_channel(channel)


def update_channel(channel_id: int, payload: dict) -> dict:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None:
        raise NotFoundError("Channel not found.")
    _ensure_owner_or_admin(channel.workspace_id, user.id)
    description = payload.get("description")
    topic = payload.get("topic")
    if topic is not None and not topic.strip():
        raise ValidationError("Topic cannot be empty.")
    updated = ChannelRepository.update(
        channel_id,
        description=description,
        topic=topic,
    )
    AuditRepository.create(
        actor_id=user.id,
        action="channel.update_settings",
        detail=f"Updated channel {channel.name}",
        workspace_id=channel.workspace_id,
        channel_id=channel.id,
    )
    return _serialize_channel(updated)


def archive_channel(channel_id: int) -> dict:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None:
        raise NotFoundError("Channel not found.")
    _ensure_owner_or_admin(channel.workspace_id, user.id)
    if channel.archived:
        raise ConflictError("Channel is already archived.")
    updated = ChannelRepository.update(channel_id, archived=True)
    AuditRepository.create(
        actor_id=user.id,
        action="channel.archive",
        detail=f"Archived channel {channel.name}",
        workspace_id=channel.workspace_id,
        channel_id=channel.id,
    )
    return _serialize_channel(updated)


def unarchive_channel(channel_id: int) -> dict:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None:
        raise NotFoundError("Channel not found.")
    _ensure_owner_or_admin(channel.workspace_id, user.id)
    if not channel.archived:
        raise ConflictError("Channel is not archived.")
    updated = ChannelRepository.update(channel_id, archived=False)
    AuditRepository.create(
        actor_id=user.id,
        action="channel.unarchive",
        detail=f"Unarchived channel {channel.name}",
        workspace_id=channel.workspace_id,
        channel_id=channel.id,
    )
    return _serialize_channel(updated)


def rename_channel(channel_id: int, payload: dict) -> dict:
    import re

    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None:
        raise NotFoundError("Channel not found.")
    _ensure_owner_or_admin(channel.workspace_id, user.id)
    new_name = (payload.get("name") or "").strip().lower()
    if not re.match(r"^[a-z0-9][a-z0-9-]{1,62}$", new_name):
        raise ValidationError("Channel name must be 2-63 chars, lowercase letters/digits/hyphens.")
    if ChannelRepository.by_name_in_workspace(channel.workspace_id, new_name):
        raise ConflictError("Channel name is already in use in this workspace.")
    with_channel = ChannelRepository.update(channel_id, description=None, topic=None)
    channel_obj = channel
    from ..db import SessionLocal
    from ..models import Channel as ChannelModel

    with SessionLocal() as session:
        c = session.get(ChannelModel, channel_id)
        if c is not None:
            c.name = new_name
            session.commit()
            from datetime import datetime, timezone

            c.updated_at = datetime.now(timezone.utc)
            session.commit()
            session.refresh(c)
            channel_obj = c
    AuditRepository.create(
        actor_id=user.id,
        action="channel.rename",
        detail=f"Renamed channel to {new_name}",
        workspace_id=channel.workspace_id,
        channel_id=channel_id,
    )
    return _serialize_channel(channel_obj)


def list_audit_events(workspace_id: int) -> list[dict]:
    user = require_user()
    _ensure_owner_or_admin(workspace_id, user.id)
    events = AuditRepository.list_for_workspace(workspace_id)
    return [
        {
            "id": e.id,
            "actor_id": e.actor_id,
            "action": e.action,
            "detail": e.detail,
            "channel_id": e.channel_id,
            "created_at": e.created_at.isoformat() if e.created_at else None,
        }
        for e in events
    ]


def _ensure_owner_or_admin(workspace_id: int, user_id: int) -> None:
    member = MembershipRepository.by_user_workspace(user_id, workspace_id)
    if member is None or member.status != "active":
        raise ForbiddenError("Not a workspace member.")
    if member.role not in ("owner", "admin"):
        raise ForbiddenError("Only owners or admins may perform this action.")


def _serialize_channel(channel) -> dict:
    return {
        "id": channel.id,
        "workspace_id": channel.workspace_id,
        "name": channel.name,
        "description": channel.description,
        "is_private": channel.is_private,
        "archived": channel.archived,
        "topic": channel.topic,
        "created_at": channel.created_at.isoformat() if channel.created_at else None,
    }
