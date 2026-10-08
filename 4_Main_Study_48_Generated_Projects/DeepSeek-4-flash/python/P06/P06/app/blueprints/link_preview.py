from __future__ import annotations

from flask import Blueprint, flash, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..errors import AppError
from ..services import link_preview_service

link_preview_bp = Blueprint("link_preview", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@link_preview_bp.route("/links", methods=["GET", "POST"])
@login_required
def links_page():
    user = current_user()
    preview = None
    error = None
    if request.method == "POST":
        try:
            preview = link_preview_service.fetch(user, request.form.get("url", ""))["preview"]
            flash("Link preview generated.", "success")
        except AppError as app_error:
            error = app_error.message
            flash(error, "error")
    return render_template(
        "links.html",
        preview=preview,
        error=error,
        data=link_preview_service.list_recent(user),
    )


@link_preview_bp.route("/api/chat/link_preview", methods=["GET"])
@api_login_required
def link_preview_list():
    user = current_user()
    url = request.args.get("url")
    if url:
        return api_success(link_preview_service.fetch(user, url))
    return api_success(link_preview_service.list_recent(user))


@link_preview_bp.route("/api/chat/link_preview", methods=["POST"])
@api_login_required
def link_preview_create():
    user = current_user()
    data = _request_json()
    result = link_preview_service.fetch(user, data.get("url", ""))
    return api_success(result, status=201)


@link_preview_bp.route("/api/chat/link_preview/<int:preview_id>", methods=["PATCH"])
@api_login_required
def link_preview_update(preview_id: int):
    user = current_user()
    data = _request_json()
    result = link_preview_service.update(
        user,
        preview_id,
        data.get("title", ""),
        data.get("description", ""),
        data.get("site_name", ""),
    )
    return api_success(result)
