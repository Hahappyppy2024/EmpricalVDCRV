from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone
from typing import Any, Optional

from ..errors import AuthError, ValidationError
from ..repositories import SessionRepository, UserRepository
from .session_service import hash_password, sign_in, sign_out, verify_password

EMAIL_RE = re.compile(r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$")
USERNAME_RE = re.compile(r"^[a-zA-Z0-9_]{3,32}$")


def _validate_payload(payload: dict[str, Any], required: list[str]) -> None:
    missing = [k for k in required if not payload.get(k)]
    if missing:
        raise ValidationError(f"Missing required fields: {', '.join(missing)}")


def register_account(payload: dict[str, Any]) -> dict[str, Any]:
    _validate_payload(payload, ["email", "username", "display_name", "password"])
    email = payload["email"].strip().lower()
    username = payload["username"].strip()
    display_name = payload["display_name"].strip()
    password = payload["password"]

    if not EMAIL_RE.match(email):
        raise ValidationError("Invalid email format.")
    if not USERNAME_RE.match(username):
        raise ValidationError(
            "Username must be 3-32 characters: letters, numbers, or underscore."
        )
    if len(password) < 8:
        raise ValidationError("Password must be at least 8 characters.")
    if UserRepository.by_email(email):
        raise ValidationError("Email is already registered.")
    if UserRepository.by_username(username):
        raise ValidationError("Username is already taken.")

    user = UserRepository.create(
        email=email,
        username=username,
        display_name=display_name,
        password_hash=hash_password(password),
        is_admin=False,
    )
    sid = sign_in(user)
    return _serialize_user(user, session_id=sid)


def sign_in_account(payload: dict[str, Any]) -> dict[str, Any]:
    _validate_payload(payload, ["identifier", "password"])
    identifier = payload["identifier"].strip()
    password = payload["password"]
    user = UserRepository.by_email(identifier.lower()) or UserRepository.by_username(
        identifier
    )
    if user is None or not verify_password(password, user.password_hash):
        raise AuthError("Invalid credentials.")
    sid = sign_in(user)
    return _serialize_user(user, session_id=sid)


def sign_out_account() -> dict[str, Any]:
    sign_out()
    return {"status": "signed_out"}


def get_current_account() -> Optional[dict[str, Any]]:
    from .session_service import current_user

    user = current_user()
    if user is None:
        return None
    return _serialize_user(user)


def request_reset(payload: dict[str, Any]) -> dict[str, Any]:
    _validate_payload(payload, ["email"])
    user = UserRepository.by_email(payload["email"].strip().lower())
    if user is None:
        return {"status": "ok", "reset_token": None}
    token = _issue_reset_token()
    expires_at = datetime.now(timezone.utc) + timedelta(hours=2)
    UserRepository.set_reset_token(user.id, token, expires_at)
    return {"status": "ok", "reset_token": token, "expires_at": expires_at.isoformat()}


def perform_reset(payload: dict[str, Any]) -> dict[str, Any]:
    _validate_payload(payload, ["token", "new_password"])
    user = UserRepository.by_reset_token(payload["token"].strip())
    if (
        user is None
        or user.reset_expires_at is None
        or user.reset_expires_at < datetime.now(timezone.utc)
    ):
        raise ValidationError("Reset token is invalid or expired.")
    if len(payload["new_password"]) < 8:
        raise ValidationError("Password must be at least 8 characters.")
    UserRepository.set_password_hash(user.id, hash_password(payload["new_password"]))
    return {"status": "password_reset"}


def update_profile(payload: dict[str, Any]) -> dict[str, Any]:
    from .session_service import require_user

    user = require_user()
    display_name = payload.get("display_name")
    avatar_seed = payload.get("avatar_seed")
    updated = UserRepository.update_profile(
        user_id=user.id,
        display_name=display_name,
        avatar_seed=avatar_seed,
    )
    if updated is None:
        raise ValidationError("Profile update failed.")
    return _serialize_user(updated)


def list_sessions() -> list[dict[str, Any]]:
    from .session_service import require_user

    user = require_user()
    records = SessionRepository.list_for_user(user.id)
    return [
        {
            "id": r.id,
            "ip_address": r.ip_address,
            "user_agent": r.user_agent,
            "created_at": r.created_at.isoformat(),
            "expires_at": r.expires_at.isoformat(),
            "revoked": r.revoked,
        }
        for r in records
    ]


def _issue_reset_token() -> str:
    import secrets

    return secrets.token_urlsafe(24)


def _serialize_user(user, *, session_id: Optional[str] = None) -> dict[str, Any]:
    return {
        "id": user.id,
        "email": user.email,
        "username": user.username,
        "display_name": user.display_name,
        "is_admin": user.is_admin,
        "avatar_seed": user.avatar_seed,
        "created_at": user.created_at.isoformat() if user.created_at else None,
        "session_id": session_id,
    }
