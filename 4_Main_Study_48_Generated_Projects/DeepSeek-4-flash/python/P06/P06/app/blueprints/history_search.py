from __future__ import annotations

from flask import Blueprint, render_template, request

from ..auth import api_login_required, api_success, current_user, login_required
from ..services import search_service

history_bp = Blueprint("history_search", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@history_bp.route("/search")
@login_required
def search_page():
    return render_template("chat.html", search_mode=True)


@history_bp.route("/api/chat/message_history_and_search", methods=["GET"])
@api_login_required
def history_search_list():
    user = current_user()
    args = request.args
    include_dms = args.get("include_dms", "true").lower() in ("1", "true", "yes")
    result = search_service.search(
        user,
        args.get("slug"),
        args.get("q", ""),
        date_from=args.get("date_from"),
        date_to=args.get("date_to"),
        include_dms=include_dms,
        limit=min(args.get("limit", 100, type=int), 200),
    )
    result["saved_searches"] = search_service.list_saved(user)["saved_searches"]
    return api_success(result)


@history_bp.route("/api/chat/message_history_and_search", methods=["POST"])
@api_login_required
def history_search_create():
    data = _request_json()
    result = search_service.save(current_user(), data.get("name", ""), data.get("query", ""))
    return api_success(result, status=201)


@history_bp.route("/api/chat/message_history_and_search/<int:search_id>", methods=["PATCH"])
@api_login_required
def history_search_update(search_id: int):
    user = current_user()
    data = _request_json()
    if data.get("action") == "delete":
        result = search_service.delete_saved(user, search_id)
    else:
        result = search_service.update_saved(user, search_id, data.get("name", ""), data.get("query", ""))
    return api_success(result)
