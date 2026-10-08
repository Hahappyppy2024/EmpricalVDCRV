from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import frontend_service

frontend_bp = Blueprint("frontend_api", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@frontend_bp.route("/frontend")
@login_required
def frontend_page():
    return render_template("frontend.html")


@frontend_bp.route("/api/chat/frontend_api_integration", methods=["GET"])
@api_login_required
def frontend_state():
    user = current_user()
    config = frontend_service.config(user)
    events = frontend_service.list(user)
    config.update(events)
    return api_success(config)


@frontend_bp.route("/api/chat/frontend_api_integration", methods=["POST"])
@api_login_required
def frontend_event():
    data = _request_json()
    result = frontend_service.record(current_user(), data.get("event_type", ""), data.get("payload"))
    return api_success(result, status=201)


@frontend_bp.route("/api/chat/frontend_api_integration/<int:event_id>", methods=["PATCH"])
@api_login_required
def frontend_ack(event_id: int):
    result = frontend_service.acknowledge(current_user(), event_id)
    return api_success(result)
