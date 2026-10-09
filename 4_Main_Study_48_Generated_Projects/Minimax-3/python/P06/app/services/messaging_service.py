from __future__ import annotations

from ..errors import ForbiddenError, NotFoundError, ValidationError
from ..repositories import (
    ChannelRepository,
    MembershipRepository,
    MessageRepository,
)
from .session_service import require_user


def send_message(channel_id: int, payload: dict) -> dict:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None or channel.archived:
        raise NotFoundError("Channel not found or archived.")
    _ensure_can_access(channel, user.id)
    body = (payload.get("body") or "").strip()
    if not body:
        raise ValidationError("Message body is required.")
    if len(body) > 4000:
        raise ValidationError("Message body exceeds 4000 characters.")
    client_id = (payload.get("client_id") or "").strip()
    topic = payload.get("topic") or channel.topic
    existing = None
    if client_id:
        existing = MessageRepository.by_client_id(channel_id, client_id)
    if existing is not None:
        return _serialize_message(existing, deduplicated=True)
    message = MessageRepository.create(
        channel_id=channel_id,
        sender_id=user.id,
        body=body,
        topic=topic,
        client_id=client_id,
    )
    return _serialize_message(message)


def edit_message(message_id: int, payload: dict) -> dict:
    user = require_user()
    message = MessageRepository.by_id(message_id)
    if message is None or message.deleted:
        raise NotFoundError("Message not found.")
    if message.sender_id != user.id and not user.is_admin:
        raise ForbiddenError("Cannot edit another user's message.")
    new_body = (payload.get("body") or "").strip()
    if not new_body:
        raise ValidationError("Message body is required.")
    if len(new_body) > 4000:
        raise ValidationError("Message body exceeds 4000 characters.")
    updated = MessageRepository.update_body(message_id, new_body)
    return _serialize_message(updated)


def delete_message(message_id: int) -> dict:
    user = require_user()
    message = MessageRepository.by_id(message_id)
    if message is None or message.deleted:
        raise NotFoundError("Message not found.")
    if message.sender_id != user.id and not user.is_admin:
        raise ForbiddenError("Cannot delete another user's message.")
    MessageRepository.mark_deleted(message_id)
    return {"status": "deleted", "id": message_id}


def list_messages(channel_id: int, before_id: int | None = None, limit: int = 50) -> list[dict]:
    user = require_user()
    channel = ChannelRepository.by_id(channel_id)
    if channel is None or channel.archived:
        raise NotFoundError("Channel not found or archived.")
    _ensure_can_access(channel, user.id)
    messages = MessageRepository.list_in_channel(channel_id, limit=limit, before_id=before_id)
    return [_serialize_message(m) for m in messages if not m.deleted]


def _ensure_can_access(channel, user_id: int) -> None:
    member = MembershipRepository.by_user_workspace(user_id, channel.workspace_id)
    if member is None or member.status != "active":
        raise ForbiddenError("Not a workspace member.")
    if channel.is_private:
        if member.role not in ("owner", "admin", "member"):
            raise ForbiddenError("No access to private channel.")


def _serialize_message(m, *, deduplicated: bool = False) -> dict:
    return {
        "id": m.id,
        "channel_id": m.channel_id,
        "sender_id": m.sender_id,
        "body": m.body if not m.deleted else "(deleted)",
        "topic": m.topic,
        "client_id": m.client_id,
        "edited": m.edited,
        "deleted": m.deleted,
        "delivery_state": m.delivery_state,
        "created_at": m.created_at.isoformat() if m.created_at else None,
        "updated_at": m.updated_at.isoformat() if m.updated_at else None,
        "deduplicated": deduplicated,
    }
