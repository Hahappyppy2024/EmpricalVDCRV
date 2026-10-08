"""Authentication helpers: server-side sessions identified by an HTTP-only cookie."""

from __future__ import annotations

import secrets
from functools import wraps

from flask import current_app, g, jsonify, redirect, request, url_for

from .repositories import session_repo


def load_current_user() -> None:
    g.user = None
    g.session = None
    token = request.cookies.get(current_app.config["SESSION_COOKIE_NAME"])
    if token:
        auth_session = session_repo.get_active(token)
        if auth_session and auth_session.user and auth_session.user.status == "active":
            g.user = auth_session.user
            g.session = auth_session


def current_user():
    return getattr(g, "user", None)


def current_session():
    return getattr(g, "session", None)


def new_session_token(user) -> str:
    return secrets.token_urlsafe(32)


def create_session(user, ip: str = "", user_agent: str = "") -> str:
    token = new_session_token(user)
    session_repo.create(
        user.id,
        token,
        current_app.config["SESSION_TTL_SECONDS"],
        ip_address=ip,
        user_agent=user_agent,
    )
    return token


def set_session_cookie(response, token: str) -> None:
    config = current_app.config
    response.set_cookie(
        config["SESSION_COOKIE_NAME"],
        token,
        httponly=True,
        samesite="Lax",
        secure=config["COOKIE_SECURE"],
        max_age=config["SESSION_TTL_SECONDS"],
        path="/",
    )


def clear_session_cookie(response) -> None:
    response.delete_cookie(current_app.config["SESSION_COOKIE_NAME"], path="/")


def destroy_session() -> None:
    auth_session = current_session()
    if auth_session:
        session_repo.invalidate(auth_session)


def api_error(status: int, code: str, message: str) -> tuple:
    return (
        jsonify({"ok": False, "error": {"code": code, "message": message}}),
        status,
    )


def api_success(data=None, status: int = 200) -> tuple:
    return jsonify({"ok": True, "data": data}), status


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if current_user() is None:
            return redirect(url_for("accounts.login_page", next=request.path))
        return view(*args, **kwargs)

    return wrapper


def api_login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if current_user() is None:
            return api_error(401, "unauthorized", "Authentication required")
        return view(*args, **kwargs)

    return wrapper
