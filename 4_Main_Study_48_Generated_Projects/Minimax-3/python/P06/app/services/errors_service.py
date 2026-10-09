from __future__ import annotations

from ..errors import ValidationError
from ..repositories import ErrorLogRepository
from .session_service import current_user


def report_error(payload: dict) -> dict:
    user = current_user()
    code = (payload.get("code") or "client_error").strip()
    message = (payload.get("message") or "Unspecified client error.").strip()[:255]
    if not code or not message:
        raise ValidationError("code and message are required.")
    source = (payload.get("source") or "client").strip()[:64]
    entry = ErrorLogRepository.create(
        code=code, message=message, user_id=user.id if user else None, source=source
    )
    return {
        "id": entry.id,
        "code": entry.code,
        "message": entry.message,
        "user_id": entry.user_id,
        "source": entry.source,
        "created_at": entry.created_at.isoformat() if entry.created_at else None,
    }


def list_errors() -> list[dict]:
    entries = ErrorLogRepository.list_all()
    return [
        {
            "id": e.id,
            "user_id": e.user_id,
            "code": e.code,
            "message": e.message,
            "source": e.source,
            "created_at": e.created_at.isoformat() if e.created_at else None,
        }
        for e in entries
    ]
