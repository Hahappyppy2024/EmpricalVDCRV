from __future__ import annotations

import re

from ..errors import ForbiddenError, NotFoundError, ValidationError
from ..repositories import (
    ChannelRepository,
    MembershipRepository,
    WorkspaceRepository,
)
from .session_service import require_user

NAME_RE = re.compile(r"^[a-z0-9][a-z0-9-]{1,62}$")


def _payload(payload, required):
    missing = [k for k in required if not payload.get(k) and payload.get(k) != False]
    if missing:
        raise ValidationError(f"Missing required fields: {', '.join(missing)}")


def list_workspaces() -> list[dict]:
    from .session_service import current_user

    user = current_user()
    if user is not None:
        workspaces = WorkspaceRepository.list_for_user(user.id)
    else:
        workspaces = []
    return [_serialize_workspace(ws) for ws in workspaces]


def list_channels(workspace_id: int) -> list[dict]:
    user = require_user()
    ws = WorkspaceRepository.by_id(workspace_id)
    if ws is None:
        raise NotFoundError("Workspace not found.")
    _ensure_member(ws, user.id)
    channels = ChannelRepository.list_in_workspace(workspace_id, include_private=True)
    visible = []
    for ch in channels:
        if ch.archived:
            continue
        if ch.is_private:
            member = MembershipRepository.by_user_workspace(user.id, workspace_id)
            if member is None or member.role == "guest":
                continue
        visible.append(_serialize_channel(ch))
    return visible


def create_workspace(payload: dict) -> dict:
    user = require_user()
    _payload(payload, ["name"])
    name = payload["name"].strip().lower()
    if not NAME_RE.match(name):
        raise ValidationError(
            "Workspace name must be 2-63 chars, lowercase letters/digits/hyphens."
        )
    if WorkspaceRepository.by_name(name):
        raise ValidationError("Workspace name already exists.")
    ws = WorkspaceRepository.create(
        name=name,
        description=payload.get("description", ""),
        owner_id=user.id,
    )
    MembershipRepository.create(
        user_id=user.id, workspace_id=ws.id, role="owner", invited_by=user.id
    )
    return _serialize_workspace(ws)


def create_channel(workspace_id: int, payload: dict) -> dict:
    user = require_user()
    ws = WorkspaceRepository.by_id(workspace_id)
    if ws is None:
        raise NotFoundError("Workspace not found.")
    member = MembershipRepository.by_user_workspace(user.id, workspace_id)
    if member is None or member.role not in ("owner", "admin"):
        raise ForbiddenError("Only owners or admins may create channels.")
    _payload(payload, ["name"])
    name = payload["name"].strip().lower()
    if not re.match(r"^[a-z0-9][a-z0-9-]{1,62}$", name):
        raise ValidationError("Channel name must be 2-63 chars, lowercase letters/digits/hyphens.")
    if ChannelRepository.by_name_in_workspace(workspace_id, name):
        raise ValidationError("Channel name exists in workspace.")
    channel = ChannelRepository.create(
        workspace_id=workspace_id,
        name=name,
        description=payload.get("description", ""),
        is_private=bool(payload.get("is_private", False)),
        topic=payload.get("topic", "general"),
    )
    return _serialize_channel(channel)


def workspace_detail(workspace_id: int) -> dict:
    user = require_user()
    ws = WorkspaceRepository.by_id(workspace_id)
    if ws is None:
        raise NotFoundError("Workspace not found.")
    _ensure_member(ws, user.id)
    return _serialize_workspace(ws)


def _ensure_member(ws, user_id: int) -> None:
    member = MembershipRepository.by_user_workspace(user_id, ws.id)
    if member is None or member.status != "active":
        raise ForbiddenError("User is not an active member of this workspace.")


def _serialize_workspace(ws) -> dict:
    return {
        "id": ws.id,
        "name": ws.name,
        "description": ws.description,
        "owner_id": ws.owner_id,
        "created_at": ws.created_at.isoformat() if ws.created_at else None,
    }


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
