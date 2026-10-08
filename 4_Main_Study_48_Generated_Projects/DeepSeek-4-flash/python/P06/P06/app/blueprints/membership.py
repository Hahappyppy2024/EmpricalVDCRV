from __future__ import annotations

from flask import Blueprint, flash, redirect, render_template, request, url_for

from ..auth import api_login_required, api_success, current_user, login_required
from ..errors import AppError
from ..services import membership_service

membership_bp = Blueprint("membership", __name__)


def _request_json():
    return request.get_json(silent=True) or {}


@membership_bp.route("/workspaces/<slug>/members", methods=["GET", "POST"])
@login_required
def members_page(slug: str):
    user = current_user()
    data = membership_service.list(user, slug)
    if request.method == "POST":
        action = request.form.get("action", "invite")
        try:
            if action == "invite":
                membership_service.invite(
                    user, slug, request.form.get("email", ""), request.form.get("role", "member")
                )
                flash("Invitation sent.", "success")
            elif action == "change_role":
                membership_service.change_role(
                    user, slug, int(request.form.get("target_user_id", 0)), request.form.get("role", "member")
                )
                flash("Role updated.", "success")
            elif action == "remove":
                membership_service.remove_member(user, slug, int(request.form.get("target_user_id", 0)))
                flash("Member removed.", "success")
            elif action == "accept":
                membership_service.accept_invitation(user, request.form.get("token", ""))
                flash("Invitation accepted. Welcome!", "success")
                return redirect(url_for("pages.index"))
        except (AppError, ValueError) as error:
            flash(getattr(error, "message", "Invalid request"), "error")
    return render_template("members.html", slug=slug, data=data)


@membership_bp.route("/invitations/<token>", methods=["GET"])
@login_required
def invitation_page(token: str):
    return render_template("invitation.html", token=token)


@membership_bp.route("/api/chat/membership_lifecycle", methods=["GET"])
@api_login_required
def membership_list():
    slug = request.args.get("slug", "")
    if not slug:
        return api_success({"memberships": []})
    return api_success(membership_service.list(current_user(), slug))


@membership_bp.route("/api/chat/membership_lifecycle", methods=["POST"])
@api_login_required
def membership_create():
    user = current_user()
    data = _request_json()
    action = data.get("action", "invite")
    slug = data.get("slug", "")
    if action == "invite":
        result = membership_service.invite(user, slug, data.get("email", ""), data.get("role", "member"))
    elif action == "change_role":
        result = membership_service.change_role(user, slug, int(data.get("user_id", 0)), data.get("role", "member"))
    elif action == "remove":
        result = membership_service.remove_member(user, slug, int(data.get("user_id", 0)))
    elif action == "accept":
        result = membership_service.accept_invitation(user, data.get("token", ""))
    else:
        raise AppError("Unknown action", "validation_error")
    return api_success(result, status=201)


@membership_bp.route("/api/chat/membership_lifecycle/<int:record_id>", methods=["PATCH"])
@api_login_required
def membership_update(record_id: int):
    user = current_user()
    data = _request_json()
    action = data.get("action", "change_role")
    slug = data.get("slug", "")
    if action == "change_role":
        result = membership_service.change_role(user, slug, record_id, data.get("role", "member"))
    elif action == "remove":
        result = membership_service.remove_member(user, slug, record_id)
    else:
        raise AppError("Unknown action", "validation_error")
    return api_success(result)
