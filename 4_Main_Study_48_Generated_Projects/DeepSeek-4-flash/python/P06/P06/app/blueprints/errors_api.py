from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import error_service

errors_bp = Blueprint("errors_api", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@errors_bp.route("/errors")
@login_required
def errors_page():
    return render_template("errors_page.html")


@errors_bp.route("/api/chat/errors", methods=["GET"])
@api_login_required
def errors_list():
    return api_success(error_service.list(current_user()))


@errors_bp.route("/api/chat/errors", methods=["POST"])
def errors_report():
    user = current_user()
    data = _request_json()
    result = error_service.report(
        user, data.get("code", ""), data.get("message", ""), data.get("path", ""), data.get("details")
    )
    return api_success(result, status=201)


@errors_bp.route("/api/chat/errors/<int:record_id>", methods=["PATCH"])
@api_login_required
def errors_acknowledge(record_id: int):
    result = error_service.acknowledge(current_user(), record_id)
    return api_success(result)
