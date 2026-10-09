"""Authentication helpers: signup, login, logout, password hashing."""
from __future__ import annotations

import secrets
from datetime import timedelta

from werkzeug.security import check_password_hash, generate_password_hash

from ..extensions import db
from ..models import Session as SessionModel, User, utcnow
from ..utils import ApiError, is_safe_string


SESSION_TTL_HOURS = 8


def hash_password(password: str) -> str:
    return generate_password_hash(password, method="pbkdf2:sha256", salt_length=16)


def verify_password(password: str, password_hash: str) -> bool:
    return check_password_hash(password_hash, password)


def _issue_session(user: User, ip_address: str | None, user_agent: str | None) -> SessionModel:
    token = secrets.token_urlsafe(48)
    expires_at = utcnow() + timedelta(hours=SESSION_TTL_HOURS)
    record = SessionModel(
        token=token,
        user_id=user.id,
        issued_at=utcnow(),
        expires_at=expires_at,
        ip_address=ip_address,
        user_agent=user_agent,
        revoked=False,
    )
    db.session.add(record)
    user.last_login_at = utcnow()
    db.session.commit()
    return record


def register_user(
    *,
    username: str,
    email: str,
    password: str,
    display_name: str,
    role: str,
) -> User:
    username_v = is_safe_string(username, max_length=64, field="username").lower()
    email_v = is_safe_string(email, max_length=255, field="email").lower()
    display_v = is_safe_string(display_name, max_length=128, field="display_name")
    password_v = is_safe_string(password, max_length=128, field="password")
    if len(password_v) < 6:
        raise ApiError("Password must be at least 6 characters", status=400, code="bad_request")
    if role not in {"analyst", "viewer"}:
        raise ApiError("Self-registration allows only analyst or viewer roles", status=400, code="bad_request")

    if db.session.query(User).filter_by(username=username_v).first():
        raise ApiError("Username already taken", status=409, code="conflict")
    if db.session.query(User).filter_by(email=email_v).first():
        raise ApiError("Email already registered", status=409, code="conflict")

    user = User(
        username=username_v,
        email=email_v,
        display_name=display_v,
        password_hash=hash_password(password_v),
        role=role,
        is_active=True,
    )
    db.session.add(user)
    db.session.commit()
    return user


def authenticate(*, username: str, password: str, ip: str | None, user_agent: str | None) -> tuple[User, SessionModel]:
    username_v = is_safe_string(username, max_length=64, field="username").lower()
    user = db.session.query(User).filter_by(username=username_v).first()
    if user is None or not user.is_active:
        raise ApiError("Invalid credentials", status=401, code="unauthenticated")
    if not verify_password(password, user.password_hash):
        raise ApiError("Invalid credentials", status=401, code="unauthenticated")
    record = _issue_session(user, ip, user_agent)
    return user, record


def revoke_session(token: str) -> bool:
    record = db.session.query(SessionModel).filter_by(token=token).first()
    if record is None:
        return False
    record.revoked = True
    db.session.commit()
    return True


def revoke_user_sessions(user_id: int) -> int:
    affected = (
        db.session.query(SessionModel)
        .filter(SessionModel.user_id == user_id, SessionModel.revoked.is_(False))
        .update({SessionModel.revoked: True})
    )
    db.session.commit()
    return affected
