from __future__ import annotations

import json

from ..errors import ValidationError
from ..repositories import FrontendStateRepository
from .session_service import require_user

ALLOWED_STATUSES = {"loading", "success", "empty", "error", "typing", "idle"}


def record_state(payload: dict) -> dict:
    user = require_user()
    context = (payload.get("context") or "").strip()
    status = (payload.get("status") or "").strip()
    if not context or not status:
        raise ValidationError("context and status are required.")
    if status not in ALLOWED_STATUSES:
        raise ValidationError(f"status must be one of {sorted(ALLOWED_STATUSES)}.")
    extra = payload.get("payload") or {}
    if not isinstance(extra, dict):
        raise ValidationError("payload must be an object.")
    extra_text = json.dumps(extra, ensure_ascii=False)
    record = FrontendStateRepository.upsert(
        user_id=user.id, context=context, status=status, payload=extra_text
    )
    return _serialize_state(record)


def list_states() -> list[dict]:
    user = require_user()
    states = FrontendStateRepository.list_for_user(user.id)
    return [_serialize_state(s) for s in states]


def _serialize_state(s) -> dict:
    try:
        payload = json.loads(s.payload)
    except (TypeError, ValueError):
        payload = {}
    return {
        "id": s.id,
        "user_id": s.user_id,
        "context": s.context,
        "status": s.status,
        "payload": payload,
        "updated_at": s.updated_at.isoformat() if s.updated_at else None,
    }
