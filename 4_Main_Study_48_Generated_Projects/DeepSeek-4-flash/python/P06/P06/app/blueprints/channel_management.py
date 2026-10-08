from __future__ import annotations

from flask import Blueprint, flash, redirect, render_template, request, url_for

from ..auth import api_login_required, api_success, current_user, login_required
from ..errors import AppError
from ..services import channel_management_service

channel_management_bp = Blueprint("channel_management", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@channel_management_bp.route("/workspaces/<slug>/channels", methods=["GET", "POST"])
@login_required
def channels_page(slug: str):
    user = current_user()
    data = channel_management_service.list(user, slug)
    if request.method == "POST":
        action = request.form.get("action", "rename")
        try:
            channel_id = int(request.form.get("channel_id", 0))
            if action == "rename":
                channel_management_service.rename(user, slug, channel_id, request.form.get("name", ""))
                flash("Channel renamed.", "success")
            elif action == "archive":
                channel_management_service.archive(user, slug, channel_id, True)
                flash("Channel archived.", "success")
            elif action == "unarchive":
                channel_management_service.archive(user, slug, channel_id, False)
                flash("Channel restored.", "success")
            elif action == "configure":
                visibility = request.form.get("visibility")
                channel_management_service.configure(
                    user, slug, channel_id, request.form.get("description"), visibility or None
                )
                flash("Channel settings updated.", "success")
        except (AppError, ValueError) as error:
            flash(getattr(error, "message", "Invalid request"), "error")
        return redirect(url_for("channel_management.channels_page", slug=slug))
    return render_template("channels_admin.html", slug=slug, data=data)


@channel_management_bp.route("/audit")
@login_required
def audit_page():
    user = current_user()
    slug = request.args.get("slug", "")
    data = None
    if slug:
        try:
            data = channel_management_service.audit(user, slug)
        except AppError as error:
            flash(error.message, "error")
    return render_template("audit.html", data=data)


@channel_management_bp.route("/api/chat/channel_management", methods=["GET"])
@api_login_required
def channel_management_list():
    slug = request.args.get("slug", "")
    return api_success(channel_management_service.list(current_user(), slug))


@channel_management_bp.route("/api/chat/channel_management", methods=["POST"])
@api_login_required
def channel_management_create():
    user = current_user()
    data = _request_json()
    slug = data.get("slug", "")
    action = data.get("action", "rename")
    channel_id = int(data.get("channel_id", 0))
    if action == "rename":
        result = channel_management_service.rename(user, slug, channel_id, data.get("name", ""))
    elif action == "archive":
        result = channel_management_service.archive(user, slug, channel_id, True)
    elif action == "unarchive":
        result = channel_management_service.archive(user, slug, channel_id, False)
    elif action == "configure":
        result = channel_management_service.configure(
            user, slug, channel_id, data.get("description"), data.get("visibility")
        )
    else:
        raise AppError("Unknown action", "validation_error")
    return api_success(result, status=201)


@channel_management_bp.route("/api/chat/channel_management/<int:record_id>", methods=["PATCH"])
@api_login_required
def channel_management_update(record_id: int):
    user = current_user()
    data = _request_json()
    slug = data.get("slug", "")
    result = channel_management_service.configure(
        user, slug, record_id, data.get("description"), data.get("visibility")
    )
    return api_success(result)


@channel_management_bp.route("/api/chat/channel_management/audit", methods=["GET"])
@api_login_required
def channel_management_audit():
    slug = request.args.get("slug", "")
    return api_success(channel_management_service.audit(current_user(), slug))
