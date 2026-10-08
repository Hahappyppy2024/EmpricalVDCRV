from __future__ import annotations

from flask import Blueprint, flash, jsonify, redirect, render_template, request, url_for

from ..auth import (
    api_error,
    api_login_required,
    api_success,
    clear_session_cookie,
    create_session,
    current_user,
    destroy_session,
    login_required,
    set_session_cookie,
)
from ..errors import AppError
from ..services import auth_service

accounts_bp = Blueprint("accounts", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


# ---------------------------------------------------------------- pages


@accounts_bp.route("/login", methods=["GET", "POST"])
def login_page():
    if current_user() is not None:
        return redirect(url_for("pages.index"))
    if request.method == "POST":
        try:
            data = request.form
            result = auth_service.login(
                data.get("username_or_email", ""),
                data.get("password", ""),
            )
            token = create_session(
                _find_user(result), ip=request.remote_addr or "", user_agent=request.headers.get("User-Agent") or ""
            )
            response = redirect(request.args.get("next") or url_for("pages.index"))
            set_session_cookie(response, token)
            flash("Signed in successfully.", "success")
            return response
        except AppError as error:
            flash(error.message, "error")
    return render_template("login.html")


@accounts_bp.route("/register", methods=["GET", "POST"])
def register_page():
    if current_user() is not None:
        return redirect(url_for("pages.index"))
    if request.method == "POST":
        try:
            data = request.form
            result = auth_service.register(
                data.get("username", ""),
                data.get("email", ""),
                data.get("full_name", ""),
                data.get("password", ""),
            )
            token = create_session(
                _find_user(result), ip=request.remote_addr or "", user_agent=request.headers.get("User-Agent") or ""
            )
            response = redirect(url_for("pages.index"))
            set_session_cookie(response, token)
            flash("Account created. Welcome!", "success")
            return response
        except AppError as error:
            flash(error.message, "error")
    return render_template("register.html")


@accounts_bp.route("/forgot-password", methods=["GET", "POST"])
def forgot_password_page():
    if request.method == "POST":
        email = request.form.get("email", "")
        auth_service.request_recovery(email)
        flash(
            "If an account exists for that email, a reset link has been sent.",
            "success",
        )
        return redirect(url_for("accounts.forgot_password_page"))
    return render_template("forgot_password.html")


@accounts_bp.route("/reset-password/<token>", methods=["GET", "POST"])
def reset_password_page(token: str):
    if request.method == "POST":
        try:
            result = auth_service.reset_password(token, request.form.get("new_password", ""))
            user = _find_user(result)
            flash("Password reset. Please sign in.", "success")
            return redirect(url_for("accounts.login_page"))
        except AppError as error:
            flash(error.message, "error")
    return render_template("reset_password.html", token=token)


@accounts_bp.route("/logout", methods=["POST"])
@login_required
def logout_page():
    destroy_session()
    response = redirect(url_for("accounts.login_page"))
    clear_session_cookie(response)
    flash("Signed out.", "success")
    return response


@accounts_bp.route("/profile", methods=["GET", "POST"])
@login_required
def profile_page():
    user = current_user()
    if request.method == "POST":
        action = request.form.get("action", "update")
        try:
            if action == "update":
                auth_service.update_profile(
                    user, request.form.get("full_name", ""), request.form.get("email", "")
                )
                flash("Profile updated.", "success")
            elif action == "password":
                auth_service.change_password(
                    user,
                    request.form.get("current_password", ""),
                    request.form.get("new_password", ""),
                )
                destroy_session()
                response = redirect(url_for("accounts.login_page"))
                clear_session_cookie(response)
                flash("Password changed. Please sign in again.", "success")
                return response
        except AppError as error:
            flash(error.message, "error")
    return render_template("profile.html", user=user, sessions=auth_service.sessions_for(user))


# ---------------------------------------------------------------- API


@accounts_bp.route("/api/chat/accounts", methods=["GET"])
@api_login_required
def accounts_list():
    return api_success({"accounts": [_find_user_by_id(u) for u in auth_service.all_users()]})


@accounts_bp.route("/api/chat/accounts", methods=["POST"])
def accounts_create():
    data = _request_json()
    if data.get("action") == "login":
        return accounts_login()
    if data.get("action") == "recover":
        auth_service.request_recovery(data.get("email", ""))
        return api_success(
            {"message": "If an account exists for that email, a reset link has been sent."}
        )
    if data.get("action") == "reset":
        result = auth_service.reset_password(data.get("token", ""), data.get("new_password", ""))
        return api_success(result, status=200)
    result = auth_service.register(
        data.get("username", ""),
        data.get("email", ""),
        data.get("full_name", ""),
        data.get("password", ""),
    )
    token = create_session(
        _find_user(result), ip=request.remote_addr or "", user_agent=request.headers.get("User-Agent") or ""
    )
    response = jsonify({"ok": True, "data": {"user": result["user"], "session_token": token}})
    response.status_code = 201
    set_session_cookie(response, token)
    return response


@accounts_bp.route("/api/chat/accounts/login", methods=["POST"])
def accounts_login():
    data = _request_json()
    result = auth_service.login(data.get("username_or_email", ""), data.get("password", ""))
    token = create_session(
        _find_user(result), ip=request.remote_addr or "", user_agent=request.headers.get("User-Agent") or ""
    )
    response = jsonify({"ok": True, "data": {"user": result["user"], "session_token": token}})
    set_session_cookie(response, token)
    return response


@accounts_bp.route("/api/chat/accounts/logout", methods=["POST"])
def accounts_logout():
    destroy_session()
    response = jsonify({"ok": True, "data": {"ok": True}})
    clear_session_cookie(response)
    return response


@accounts_bp.route("/api/chat/accounts/me", methods=["GET"])
@api_login_required
def accounts_me():
    return api_success({"user": current_user().to_dict(), "sessions": auth_service.sessions_for(current_user())})


@accounts_bp.route("/api/chat/accounts/<int:user_id>", methods=["PATCH"])
@api_login_required
def accounts_update(user_id: int):
    user = current_user()
    if user.id != user_id and user.role != "admin":
        return api_error(403, "access_denied", "You can only update your own account")
    target = _find_user_by_id_or_error(user_id)
    data = _request_json()
    result = auth_service.update_profile(target, data.get("full_name", ""), data.get("email", ""))
    return api_success(result)


def _find_user(result: dict):
    from ..repositories import user_repo

    return user_repo.get(result["user"]["id"])


def _find_user_by_id(user_id: int):
    from ..repositories import user_repo

    return user_repo.get(user_id)


def _find_user_by_id_or_error(user_id: int):
    from ..repositories import user_repo

    user = user_repo.get(user_id)
    if user is None:
        raise AppError("Account not found", "not_found", 404)
    return user
