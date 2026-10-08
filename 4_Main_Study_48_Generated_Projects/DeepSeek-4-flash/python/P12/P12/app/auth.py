"""Session-based authentication helpers.

Server-side sessions identified by an HTTP-only cookie; session records are
persisted in SQLite.
"""
from datetime import datetime
from functools import wraps

from flask import g, jsonify, redirect, request, url_for

from . import models
from .data_access import Repository


def get_db():
    """Return the request-scoped database session."""
    from .extensions import SessionLocal

    if "db" not in g:
        g.db = SessionLocal()
    return g.db


def get_repo():
    return Repository(get_db())


def current_user():
    """Resolve the signed-in user from the auth cookie, or None."""
    if "current_user" in g:
        return g.current_user
    token = request.cookies.get(_auth_cookie_name())
    user = None
    if token:
        repo = get_repo()
        session = repo.session_by_token(token)
        if session and session.expires_at > datetime.utcnow():
            candidate = repo.get(models.User, session.user_id)
            if candidate and candidate.enabled:
                user = candidate
    g.current_user = user
    return user


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        user = current_user()
        if user is None:
            if request.path.startswith("/api/"):
                return (
                    jsonify({"ok": False, "error": "Authentication required"}),
                    401,
                )
            return redirect(url_for("pages.login", next=request.path))
        return view(*args, **kwargs)

    return wrapper


def role_required(*roles):
    def decorator(view):
        @wraps(view)
        def wrapper(*args, **kwargs):
            user = current_user()
            if user is None:
                if request.path.startswith("/api/"):
                    return (
                        jsonify({"ok": False, "error": "Authentication required"}),
                        401,
                    )
                return redirect(url_for("pages.login"))
            if user.role not in roles:
                if request.path.startswith("/api/"):
                    return (
                        jsonify({"ok": False, "error": "Forbidden: role not permitted"}),
                        403,
                    )
                return (
                    "403 Forbidden: your role does not permit this action",
                    403,
                )
            return view(*args, **kwargs)

        return wrapper

    return decorator


def _auth_cookie_name():
    from flask import current_app

    return current_app.config["AUTH_COOKIE_NAME"]


def set_session_cookie(response, token):
    """Set the HTTP-only auth cookie with secure flags."""
    max_age = current_app_config()["SESSION_LIFETIME_DAYS"] * 86400
    response.set_cookie(
        _auth_cookie_name(),
        token,
        max_age=max_age,
        httponly=True,
        samesite="Lax",
    )
    return response


def current_app_config():
    from flask import current_app

    return current_app.config


def clear_session_cookie(response):
    response.delete_cookie(_auth_cookie_name())
    return response
