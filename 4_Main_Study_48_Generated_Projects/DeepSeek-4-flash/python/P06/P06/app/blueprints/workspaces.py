from __future__ import annotations

from flask import Blueprint, flash, redirect, render_template, request, url_for

from ..auth import (
    api_login_required,
    api_success,
    current_user,
    login_required,
)
from ..errors import AppError
from ..services import workspace_service

workspaces_bp = Blueprint("workspaces", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@workspaces_bp.route("/workspaces", methods=["GET", "POST"])
@login_required
def workspaces_page():
    user = current_user()
    data = workspace_service.list_for(user)
    if request.method == "POST":
        try:
            result = workspace_service.create_workspace(
                user, request.form.get("name", ""), request.form.get("description", "")
            )
            flash(f"Workspace {result['workspace']['name']} created.", "success")
            return redirect(
                url_for("messaging.chat_page", slug=result["workspace"]["slug"])
            )
        except AppError as error:
            flash(error.message, "error")
    return render_template("workspaces.html", data=data)


@workspaces_bp.route("/api/chat/workspaces_and_channels", methods=["GET"])
@api_login_required
def workspaces_and_channels_list():
    return api_success(workspace_service.list_for(current_user()))


@workspaces_bp.route("/api/chat/workspaces_and_channels", methods=["POST"])
@api_login_required
def workspaces_and_channels_create():
    user = current_user()
    data = _request_json()
    if data.get("type") == "channel":
        result = workspace_service.create_channel(
            user,
            data.get("slug", ""),
            data.get("name", ""),
            data.get("description", ""),
            data.get("visibility", "public"),
        )
    else:
        result = workspace_service.create_workspace(
            user, data.get("name", ""), data.get("description", "")
        )
    return api_success(result, status=201)


@workspaces_bp.route("/api/chat/workspaces_and_channels/<int:record_id>", methods=["PATCH"])
@api_login_required
def workspaces_and_channels_update(record_id: int):
    user = current_user()
    data = _request_json()
    slug = data.get("slug", "")
    kind = data.get("type", "workspace")
    if kind == "channel":
        result = workspace_service.update_channel(
            user, slug, record_id, data.get("name", ""), data.get("description", "")
        )
    else:
        result = workspace_service.update_workspace(
            user, slug, data.get("name", ""), data.get("description", "")
        )
    return api_success(result)
