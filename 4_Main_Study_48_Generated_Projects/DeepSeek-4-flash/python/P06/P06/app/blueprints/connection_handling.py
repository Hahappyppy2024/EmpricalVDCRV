from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import connection_service

connection_bp = Blueprint("connection_handling", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@connection_bp.route("/connection")
@login_required
def connection_page():
    return render_template("connection.html")


@connection_bp.route("/api/chat/connection_and_message_handling", methods=["GET"])
@api_login_required
def connection_state():
    return api_success(connection_service.get_state(current_user()))


@connection_bp.route("/api/chat/connection_and_message_handling", methods=["POST"])
@api_login_required
def connection_event():
    user = current_user()
    data = _request_json()
    result = connection_service.record_event(
        user,
        data.get("event_type", ""),
        session_id=data.get("session_id", ""),
        message_id=int(data.get("message_id") or 0) or None,
        payload=data.get("payload"),
    )
    return api_success(result, status=201)


@connection_bp.route("/api/chat/connection_and_message_handling/<int:record_id>", methods=["PATCH"])
@api_login_required
def connection_update(record_id: int):
    user = current_user()
    data = _request_json()
    result = connection_service.record_event(
        user,
        data.get("event_type", "read"),
        session_id=data.get("session_id", ""),
        message_id=record_id,
        payload=data.get("payload"),
    )
    return api_success(result)
