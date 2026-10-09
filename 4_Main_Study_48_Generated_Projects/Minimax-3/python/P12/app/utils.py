"""Shared utility helpers (validation, errors, decorators)."""
from __future__ import annotations

from datetime import datetime
from functools import wraps
from typing import Any

from flask import jsonify, request, session

from .extensions import db
from .models import Session as SessionModel, User, utcnow


ROLE_ANALYST = "analyst"
ROLE_VIEWER = "viewer"
ROLE_ADMIN = "admin"

ALL_ROLES = {ROLE_ANALYST, ROLE_VIEWER, ROLE_ADMIN}
PRIVILEGED_ROLES = {ROLE_ANALYST, ROLE_ADMIN}


class ApiError(Exception):
    def __init__(self, message: str, status: int = 400, code: str = "bad_request"):
        super().__init__(message)
        self.message = message
        self.status = status
        self.code = code


def error_response(message: str, status: int = 400, code: str = "bad_request"):
    response = jsonify({"error": {"code": code, "message": message}})
    response.status_code = status
    return response


def success_response(payload: Any, status: int = 200):
    response = jsonify({"data": payload, "status": "ok"})
    response.status_code = status
    return response


def get_current_user() -> User | None:
    token = session.get("sid")
    if not token:
        return None
    record = db.session.query(SessionModel).filter_by(token=token, revoked=False).first()
    if record is None:
        return None
    if record.expires_at < utcnow():
        record.revoked = True
        db.session.commit()
        session.clear()
        return None
    user = db.session.get(User, record.user_id)
    return user if user and user.is_active else None


def require_auth(roles: set[str] | None = None):
    allowed = roles or ALL_ROLES

    def decorator(view):
        @wraps(view)
        def wrapper(*args, **kwargs):
            user = get_current_user()
            if user is None:
                return error_response(
                    "Authentication required", status=401, code="unauthenticated"
                )
            if user.role not in allowed:
                return error_response(
                    "Insufficient privileges", status=403, code="forbidden"
                )
            request.current_user = user  # type: ignore[attr-defined]
            return view(*args, **kwargs)

        return wrapper

    return decorator


def get_request_json() -> dict[str, Any]:
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        raise ApiError("Request body must be a JSON object", status=400, code="bad_request")
    return data


def get_request_form() -> dict[str, Any]:
    form = {key: value for key, value in request.form.items()}
    if not form:
        raise ApiError("Form submission is empty", status=400, code="bad_request")
    return form


def parse_iso(value: str | None) -> datetime | None:
    if not value:
        return None
    candidate = value.replace("Z", "")
    try:
        return datetime.fromisoformat(candidate).replace(tzinfo=None)
    except ValueError as exc:
        raise ApiError(f"Invalid datetime '{value}'", status=400, code="bad_request") from exc


def is_safe_string(value: Any, *, max_length: int = 256, field: str = "value") -> str:
    if not isinstance(value, str):
        raise ApiError(f"'{field}' must be a string", status=400, code="bad_request")
    cleaned = value.strip()
    if not cleaned:
        raise ApiError(f"'{field}' is required", status=400, code="bad_request")
    if len(cleaned) > max_length:
        raise ApiError(
            f"'{field}' exceeds maximum length of {max_length}",
            status=400,
            code="bad_request",
        )
    return cleaned


def is_choice(value: Any, choices: set[str], field: str) -> str:
    candidate = is_safe_string(value, max_length=64, field=field)
    if candidate not in choices:
        raise ApiError(
            f"'{field}' must be one of {sorted(choices)}",
            status=400,
            code="bad_request",
        )
    return candidate


def is_bool(value: Any, field: str = "value") -> bool:
    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        lowered = value.lower()
        if lowered in {"true", "1", "yes"}:
            return True
        if lowered in {"false", "0", "no"}:
            return False
    if isinstance(value, (int, float)):
        return bool(value)
    raise ApiError(f"'{field}' must be a boolean", status=400, code="bad_request")


def is_int(value: Any, *, field: str = "value", minimum: int = 0, maximum: int | None = None) -> int:
    try:
        ivalue = int(value)
    except (TypeError, ValueError) as exc:
        raise ApiError(f"'{field}' must be an integer", status=400, code="bad_request") from exc
    if ivalue < minimum:
        raise ApiError(f"'{field}' must be >= {minimum}", status=400, code="bad_request")
    if maximum is not None and ivalue > maximum:
        raise ApiError(f"'{field}' must be <= {maximum}", status=400, code="bad_request")
    return ivalue
