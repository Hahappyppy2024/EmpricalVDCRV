from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import messaging_service

messaging_bp = Blueprint("messaging", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@messaging_bp.route("/chat")
@login_required
def chat_page():
    return render_template("chat.html")


@messaging_bp.route("/api/chat/real_time_messaging", methods=["GET"])
@api_login_required
def real_time_messaging_list():
    user = current_user()
    args = request.args
    before_id = args.get("before_id", type=int)
    limit = min(args.get("limit", 50, type=int), 100)
    topic_id = args.get("topic_id", type=int)
    return api_success(
        messaging_service.list(user, args.get("slug", ""), args.get("channel_id", type=int), topic_id=topic_id, before_id=before_id, limit=limit)
    )


@messaging_bp.route("/api/chat/real_time_messaging", methods=["POST"])
@api_login_required
def real_time_messaging_create():
    user = current_user()
    data = _request_json()
    result = messaging_service.send(
        user,
        data.get("slug", ""),
        int(data.get("channel_id") or 0),
        data.get("body", ""),
        client_msg_id=data.get("client_msg_id"),
        topic_id=int(data.get("topic_id") or 0) or None,
    )
    return api_success(result, status=201)


@messaging_bp.route("/api/chat/real_time_messaging/<int:message_id>", methods=["PATCH"])
@api_login_required
def real_time_messaging_update(message_id: int):
    user = current_user()
    data = _request_json()
    slug = data.get("slug", "")
    if data.get("action") == "delete":
        result = messaging_service.delete(user, slug, message_id)
    else:
        result = messaging_service.edit(user, slug, message_id, data.get("body", ""))
    return api_success(result)
