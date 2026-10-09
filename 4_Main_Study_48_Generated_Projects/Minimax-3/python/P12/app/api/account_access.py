"""DATA-01 — Account access (signup, signin, signout, current user)."""
from __future__ import annotations

from flask import Blueprint, request, session

from ..extensions import db
from ..services.auth_service import authenticate, register_user, revoke_session
from ..services.audit_service import record_audit
from ..models import User
from ..utils import (
    ApiError,
    error_response,
    get_current_user,
    get_request_json,
    require_auth,
    success_response,
)

bp = Blueprint("account_access", __name__)


@bp.get("/api/data/account_access")
@require_auth()
def get_account_access():
    user = get_current_user()
    return success_response(
        {
            "user": user.to_dict(),
            "role": user.role,
            "session_id": session.get("sid"),
        }
    )


@bp.post("/api/data/account_access")
def post_account_access():
    """``mode`` switches between ``signup`` and ``signin``."""
    payload = get_request_json()
    mode = payload.get("mode", "signin").lower()
    ip = request.remote_addr
    user_agent = (request.headers.get("User-Agent") or "")[:255]

    if mode == "signup":
        try:
            user = register_user(
                username=payload.get("username", ""),
                email=payload.get("email", ""),
                password=payload.get("password", ""),
                display_name=payload.get("display_name", ""),
                role=payload.get("role", "viewer"),
            )
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)
        record_audit(
            actor=user,
            action="account.signup",
            entity_type="user",
            entity_id=user.id,
            summary=f"Registered {user.username} ({user.role})",
        )
        session["sid"] = _auto_login_for_signup(user, ip, user_agent)
        return success_response({"user": user.to_dict()}, status=201)

    if mode == "signin":
        try:
            user, record = authenticate(
                username=payload.get("username", ""),
                password=payload.get("password", ""),
                ip=ip,
                user_agent=user_agent,
            )
        except ApiError as exc:
            return error_response(exc.message, status=exc.status, code=exc.code)
        session["sid"] = record.token
        record_audit(
            actor=user,
            action="account.signin",
            entity_type="session",
            entity_id=record.id,
            summary=f"{user.username} signed in",
        )
        return success_response({"user": user.to_dict()})

    if mode == "signout":
        token = session.get("sid")
        if token:
            revoke_session(token)
        actor = get_current_user()
        session.clear()
        record_audit(
            actor=actor,
            action="account.signout",
            entity_type="session",
            entity_id=token or "-",
            summary=f"{actor.username if actor else 'unknown'} signed out",
        )
        return success_response({"signed_out": True})

    if mode == "reset":
        # Password reset is simulated: a valid username + new password is accepted
        username = (payload.get("username") or "").strip()
        new_password = payload.get("new_password") or ""
        if not username or len(new_password) < 6:
            return error_response(
                "Reset requires a username and a new password of at least 6 characters",
                status=400,
                code="bad_request",
            )
        user = db.session.query(User).filter_by(username=username.lower()).first()
        if user is None:
            return error_response("Reset failed", status=400, code="bad_request")
        from ..services.auth_service import hash_password

        user.password_hash = hash_password(new_password)
        db.session.commit()
        record_audit(
            actor=None,
            action="account.reset",
            entity_type="user",
            entity_id=user.id,
            summary=f"Password reset attempted for {user.username}",
        )
        return success_response({"reset": True})

    return error_response(f"Unknown mode '{mode}'", status=400, code="bad_request")


@bp.patch("/api/data/account_access/<int:user_id>")
@require_auth()
def patch_account_access(user_id: int):
    actor = get_current_user()
    target = db.session.get(User, user_id)
    if target is None:
        return error_response("User not found", status=404, code="not_found")
    if actor.id != target.id and actor.role != "admin":
        return error_response("Insufficient privileges", status=403, code="forbidden")
    payload = get_request_json()
    if "display_name" in payload:
        target.display_name = payload["display_name"].strip() or target.display_name
    if "email" in payload and actor.role == "admin":
        target.email = payload["email"].strip().lower()
    if "password" in payload:
        from ..services.auth_service import hash_password

        new_password = (payload.get("password") or "").strip()
        if len(new_password) < 6:
            return error_response("Password must be at least 6 characters", status=400, code="bad_request")
        target.password_hash = hash_password(new_password)
    if "is_active" in payload and actor.role == "admin":
        target.is_active = bool(payload["is_active"])
    db.session.commit()
    record_audit(
        actor=actor,
        action="account.update",
        entity_type="user",
        entity_id=target.id,
        summary=f"{actor.username} updated {target.username}",
    )
    return success_response({"user": target.to_dict()})


def _auto_login_for_signup(user: User, ip: str | None, user_agent: str | None) -> str:
    from datetime import timedelta
    import secrets

    from ..models import Session as SessionModel, utcnow

    token = secrets.token_urlsafe(48)
    record = SessionModel(
        token=token,
        user_id=user.id,
        issued_at=utcnow(),
        expires_at=utcnow() + timedelta(hours=8),
        ip_address=ip,
        user_agent=user_agent,
        revoked=False,
    )
    db.session.add(record)
    user.last_login_at = utcnow()
    db.session.commit()
    return token
