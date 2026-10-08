from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import dm_service

dm_bp = Blueprint("direct_messages", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@dm_bp.route("/dm")
@login_required
def dm_page():
    return render_template("dms.html")


@dm_bp.route("/api/chat/direct_messages", methods=["GET"])
@api_login_required
def direct_messages_list():
    user = current_user()
    thread_id = request.args.get("thread_id", type=int)
    if thread_id:
        return api_success(dm_service.list_messages(user, thread_id))
    return api_success(dm_service.list_threads(user))


@dm_bp.route("/api/chat/direct_messages", methods=["POST"])
@api_login_required
def direct_messages_create():
    user = current_user()
    data = _request_json()
    if data.get("action") == "read":
        result = dm_service.mark_read(user, int(data.get("thread_id", 0)))
    else:
        result = dm_service.send(
            user,
            data.get("recipient", ""),
            data.get("body", ""),
            client_msg_id=data.get("client_msg_id"),
        )
    return api_success(result, status=201)


@dm_bp.route("/api/chat/direct_messages/<int:dm_id>", methods=["PATCH"])
@api_login_required
def direct_messages_update(dm_id: int):
    user = current_user()
    data = _request_json()
    if data.get("action") == "delete":
        result = dm_service.delete(user, dm_id)
    else:
        result = dm_service.edit(user, dm_id, data.get("body", ""))
    return api_success(result)
