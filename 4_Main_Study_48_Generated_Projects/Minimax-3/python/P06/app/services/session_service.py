from __future__ import annotations

from datetime import datetime, timedelta, timezone

from flask import g, request, session

from ..config import Config
from ..models import User
from ..repositories import SessionRepository, UserRepository


def current_user() -> User | None:
    user = getattr(g, "current_user", None)
    if user is not None:
        return user
    sess_id = session.get("sid")
    if not sess_id:
        return None
    record = SessionRepository.by_session_id(sess_id)
    if record is None or record.revoked:
        session.clear()
        return None
    if record.expires_at:
        expires_at = record.expires_at
        if expires_at.tzinfo is None:
            expires_at = expires_at.replace(tzinfo=timezone.utc)
        if expires_at < datetime.now(timezone.utc):
            return None
    user = UserRepository.by_id(record.user_id)
    if user is None:
        return None
    g.current_user = user
    return user


def require_user() -> User:
    user = current_user()
    if user is None:
        from ..errors import AuthError

        raise AuthError("Authentication required.")
    return user


def require_admin() -> User:
    user = require_user()
    if not user.is_admin:
        from ..errors import ForbiddenError

        raise ForbiddenError("Administrator privileges required.")
    return user


def hash_password(plain: str) -> str:
    from werkzeug.security import generate_password_hash

    return generate_password_hash(plain)


def verify_password(plain: str, hashed: str) -> bool:
    from werkzeug.security import check_password_hash

    return check_password_hash(hashed, plain)


def sign_in(user: User) -> str:
    from flask import session as flask_session

    sid = secrets_hex(32)
    SessionRepository.create(
        session_id=sid,
        user_id=user.id,
        ip_address=request.remote_addr or "0.0.0.0",
        user_agent=(request.headers.get("User-Agent") or "unknown")[:255],
        expires_at=datetime.now(timezone.utc) + timedelta(seconds=Config.SESSION_LIFETIME),
    )
    flask_session.clear()
    flask_session["sid"] = sid
    flask_session["uid"] = user.id
    flask_session.permanent = True
    return sid


def sign_out() -> None:
    from flask import session as flask_session

    sid = flask_session.get("sid")
    if sid:
        SessionRepository.revoke(sid)
    flask_session.clear()


def secrets_hex(n: int) -> str:
    import secrets

    return secrets.token_hex(n)
