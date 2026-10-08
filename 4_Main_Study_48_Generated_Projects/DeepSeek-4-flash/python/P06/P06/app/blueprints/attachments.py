from __future__ import annotations

from flask import Blueprint, render_template, request, send_file

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import attachment_service

attachments_bp = Blueprint("attachments", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


def _to_int(value):
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


@attachments_bp.route("/attachments", methods=["GET", "POST"])
@login_required
def attachments_page():
    user = current_user()
    if request.method == "POST":
        message_id = request.form.get("message_id", type=int)
        is_private = request.form.get("is_private") in ("1", "true", "on")
        result = attachment_service.upload(
            user, request.files.get("file"), is_private=is_private, message_id=message_id
        )
        return render_template(
            "attachments.html",
            data=attachment_service.list_for(user),
            last=result,
        )
    return render_template("attachments.html", data=attachment_service.list_for(user))


@attachments_bp.route("/api/chat/attachments", methods=["GET"])
@api_login_required
def attachments_list():
    return api_success(attachment_service.list_for(current_user()))


@attachments_bp.route("/api/chat/attachments", methods=["POST"])
@api_login_required
def attachments_create():
    user = current_user()
    data = _request_json()
    if data.get("kind") == "metadata":
        return api_success(
            attachment_service.upload_metadata(
                user,
                _to_int(data.get("message_id")),
                _to_int(data.get("direct_message_id")),
                data.get("filename", ""),
                _to_int(data.get("size")),
                data.get("content_type", ""),
                bool(data.get("is_private", False)),
            )
        )
    file_storage = request.files.get("file")
    form_private = request.form.get("is_private")
    is_private = (
        form_private in ("1", "true", "on") if form_private is not None
        else bool(data.get("is_private", False))
    )
    result = attachment_service.upload(
        user,
        file_storage,
        is_private=is_private,
        message_id=_to_int(data.get("message_id")) or request.form.get("message_id", type=int),
        direct_message_id=_to_int(data.get("direct_message_id")) or request.form.get("direct_message_id", type=int),
    )
    return api_success(result, status=201)


@attachments_bp.route("/api/chat/attachments/<int:attachment_id>", methods=["PATCH"])
@api_login_required
def attachments_update(attachment_id: int):
    user = current_user()
    data = _request_json()
    is_private = data.get("is_private")
    if isinstance(is_private, bool):
        result = attachment_service.update_metadata(user, attachment_id, is_private=is_private)
    else:
        result = attachment_service.get_metadata(user, attachment_id)
    return api_success(result)


@attachments_bp.route("/api/chat/attachments/<int:attachment_id>/download", methods=["GET"])
@api_login_required
def attachments_download(attachment_id: int):
    result = attachment_service.download(current_user(), attachment_id)
    attachment = result["attachment"]
    return send_file(
        result["path"],
        mimetype=attachment.content_type,
        as_attachment=True,
        download_name=attachment.filename,
    )
