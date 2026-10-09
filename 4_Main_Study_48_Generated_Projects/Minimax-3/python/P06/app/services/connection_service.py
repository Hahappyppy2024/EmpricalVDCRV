from __future__ import annotations

import secrets
from datetime import datetime, timezone

from ..errors import ValidationError
from ..repositories import ConnectionStateRepository, MessageRepository
from .session_service import require_user


def open_connection() -> dict:
    user = require_user()
    conn_id = secrets.token_hex(8)
    record = ConnectionStateRepository.open(user.id, conn_id)
    return _serialize_conn(record)


def record_event(connection_id: str, payload: dict) -> dict:
    user = require_user()
    last_event = (payload.get("last_event_id") or "").strip()
    msg_id = payload.get("message_id")
    state_payload = (payload.get("state") or "").strip()
    if not connection_id:
        raise ValidationError("connection_id is required.")
    record = ConnectionStateRepository.update_event_id(connection_id, last_event)
    if record is None:
        raise ValidationError("Unknown connection_id.")
    if msg_id is not None:
        try:
            mid = int(msg_id)
        except (TypeError, ValueError):
            raise ValidationError("message_id must be an integer.")
        message = MessageRepository.by_id(mid)
        if message and not message.deleted:
            MessageRepository.update_delivery_state(mid, state_payload or "delivered")
    return _serialize_conn(record)


def close_connection(connection_id: str) -> dict:
    user = require_user()
    record = ConnectionStateRepository.close(connection_id)
    if record is None:
        raise ValidationError("Unknown connection_id.")
    return _serialize_conn(record)


def list_connections() -> list[dict]:
    user = require_user()
    records = ConnectionStateRepository.recent_for_user(user.id)
    return [_serialize_conn(r) for r in records]


def _serialize_conn(record) -> dict:
    return {
        "id": record.id,
        "user_id": record.user_id,
        "connection_id": record.connection_id,
        "status": record.status,
        "last_event_id": record.last_event_id,
        "established_at": record.established_at.isoformat() if record.established_at else None,
        "closed_at": record.closed_at.isoformat() if record.closed_at else None,
    }
