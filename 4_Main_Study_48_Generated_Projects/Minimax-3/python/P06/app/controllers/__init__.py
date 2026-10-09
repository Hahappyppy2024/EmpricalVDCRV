from __future__ import annotations

from flask import Blueprint, jsonify, request

from ..errors import ServiceError
from ..services import (
    accounts_service,
    attachments_service,
    channel_management_service,
    connection_service,
    direct_messages_service,
    errors_service,
    frontend_api_service,
    link_preview_service,
    membership_service,
    messaging_service,
    search_service,
    workspaces_service,
)

bp = Blueprint("api", __name__)


@bp.errorhandler(ServiceError)
def _handle_service_error(exc: ServiceError):
    response = jsonify({"status": "error", "code": exc.code, "message": exc.message})
    response.status_code = exc.http_status
    return response


@bp.route("/health")
def health():
    return jsonify({"status": "ok", "service": "real_time_team_chat_system"})


@bp.route("/chat/accounts", methods=["GET", "POST"])
def accounts_collection():
    if request.method == "GET":
        from ..repositories import UserRepository
        from ..services.session_service import require_user

        try:
            require_user()
        except ServiceError:
            return jsonify({"items": []})
        items = [
            {
                "id": u.id,
                "username": u.username,
                "display_name": u.display_name,
                "email": u.email,
                "is_admin": u.is_admin,
            }
            for u in UserRepository.list_all()
        ]
        return jsonify({"items": items})
    payload = request.get_json(silent=True) or {}
    user = accounts_service.register_account(payload)
    return jsonify(user)


@bp.route("/chat/accounts/signin", methods=["POST"])
def accounts_signin():
    payload = request.get_json(silent=True) or {}
    user = accounts_service.sign_in_account(payload)
    return jsonify(user)


@bp.route("/chat/accounts/signout", methods=["POST"])
def accounts_signout():
    return jsonify(accounts_service.sign_out_account())


@bp.route("/chat/accounts/me", methods=["GET", "PATCH"])
def accounts_me():
    if request.method == "GET":
        me = accounts_service.get_current_account()
        if me is None:
            return jsonify({"status": "anonymous"})
        return jsonify(me)
    return jsonify(accounts_service.update_profile(request.get_json(silent=True) or {}))


@bp.route("/chat/accounts/password/reset/request", methods=["POST"])
def accounts_reset_request():
    return jsonify(accounts_service.request_reset(request.get_json(silent=True) or {}))


@bp.route("/chat/accounts/password/reset", methods=["POST"])
def accounts_reset_perform():
    return jsonify(accounts_service.perform_reset(request.get_json(silent=True) or {}))


@bp.route("/chat/accounts/sessions", methods=["GET"])
def accounts_sessions():
    return jsonify({"items": accounts_service.list_sessions()})


@bp.route("/chat/workspaces_and_channels", methods=["GET", "POST"])
def workspaces_collection():
    if request.method == "GET":
        return jsonify({"items": workspaces_service.list_workspaces()})
    payload = request.get_json(silent=True) or {}
    return jsonify(workspaces_service.create_workspace(payload))


@bp.route("/chat/workspaces_and_channels/<int:workspace_id>", methods=["GET"])
def workspaces_detail(workspace_id: int):
    return jsonify(workspaces_service.workspace_detail(workspace_id))


@bp.route(
    "/chat/workspaces_and_channels/<int:workspace_id>/channels",
    methods=["GET", "POST"],
)
def channels_collection(workspace_id: int):
    if request.method == "GET":
        return jsonify({"items": workspaces_service.list_channels(workspace_id)})
    return jsonify(workspaces_service.create_channel(workspace_id, request.get_json(silent=True) or {}))


@bp.route("/chat/membership_lifecycle", methods=["GET"])
def membership_root():
    return jsonify({"hint": "Use workspace-scoped endpoints below."})


@bp.route(
    "/chat/workspaces_and_channels/<int:workspace_id>/members",
    methods=["GET", "POST"],
)
def members_collection(workspace_id: int):
    if request.method == "GET":
        return jsonify({"items": membership_service.list_members(workspace_id)})
    return jsonify(membership_service.invite_member(workspace_id, request.get_json(silent=True) or {}))


@bp.route(
    "/chat/workspaces_and_channels/<int:workspace_id>/members/<int:target_user_id>",
    methods=["PATCH", "DELETE"],
)
def members_item(workspace_id: int, target_user_id: int):
    if request.method == "PATCH":
        payload = request.get_json(silent=True) or {}
        new_role = payload.get("role")
        if not new_role:
            return jsonify({"status": "error", "code": "validation_error", "message": "role is required."}), 400
        return jsonify(membership_service.change_role(workspace_id, target_user_id, new_role))
    return jsonify(membership_service.remove_member(workspace_id, target_user_id))


@bp.route(
    "/chat/membership_lifecycle/<int:workspace_id>/leave",
    methods=["POST"],
)
def membership_leave(workspace_id: int):
    return jsonify(membership_service.leave_workspace(workspace_id))


@bp.route(
    "/chat/membership_lifecycle/<int:workspace_id>/invitations",
    methods=["GET"],
)
def membership_invitations(workspace_id: int):
    return jsonify({"items": membership_service.list_invitations(workspace_id)})


@bp.route(
    "/chat/membership_lifecycle/invitations/<token>/<action>",
    methods=["POST"],
)
def membership_invitation_respond(token: str, action: str):
    return jsonify(membership_service.respond_invitation(token, action))


@bp.route("/chat/real_time_messaging", methods=["GET"])
def messaging_root():
    return jsonify({"hint": "Use channel-scoped endpoints below."})


@bp.route(
    "/chat/real_time_messaging/<int:channel_id>/messages",
    methods=["GET", "POST"],
)
def messages_collection(channel_id: int):
    if request.method == "GET":
        before = request.args.get("before_id", type=int)
        limit = request.args.get("limit", default=50, type=int)
        return jsonify({"items": messaging_service.list_messages(channel_id, before, limit)})
    return jsonify(messaging_service.send_message(channel_id, request.get_json(silent=True) or {}))


@bp.route(
    "/chat/real_time_messaging/<int:message_id>",
    methods=["PATCH", "DELETE"],
)
def messages_item(message_id: int):
    if request.method == "PATCH":
        return jsonify(messaging_service.edit_message(message_id, request.get_json(silent=True) or {}))
    return jsonify(messaging_service.delete_message(message_id))


@bp.route("/chat/message_history_and_search", methods=["GET", "POST"])
def message_search():
    if request.method == "GET":
        query = request.args.get("query", "")
        channel_id = request.args.get("channel_id", type=int)
        start = request.args.get("start")
        end = request.args.get("end")
        limit = request.args.get("limit", type=int)
        payload = {"query": query, "channel_id": channel_id, "start": start, "end": end, "limit": limit}
    else:
        payload = request.get_json(silent=True) or {}
    return jsonify(search_service.search_messages(payload))


@bp.route("/chat/direct_messages", methods=["GET"])
def direct_messages_root():
    return jsonify({"items": direct_messages_service.list_threads()})


@bp.route("/chat/direct_messages/<int:other_user_id>", methods=["GET", "POST"])
def direct_messages_thread(other_user_id: int):
    if request.method == "GET":
        return jsonify(direct_messages_service.open_thread(other_user_id))
    return jsonify(direct_messages_service.send_dm(other_user_id, request.get_json(silent=True) or {}))


@bp.route("/chat/direct_messages/<path:thread_key>/read", methods=["POST"])
def direct_messages_read(thread_key: str):
    return jsonify(direct_messages_service.mark_thread_read(thread_key))


@bp.route("/chat/attachments", methods=["GET", "POST"])
def attachments_collection():
    if request.method == "GET":
        return jsonify({"items": attachments_service.list_attachments()})
    file_storage = request.files.get("file")
    message_id = request.form.get("message_id", type=int)
    return jsonify(attachments_service.upload_attachment(file_storage, message_id))


@bp.route("/chat/attachments/<int:attachment_id>/download", methods=["GET"])
def attachments_download(attachment_id: int):
    from flask import Response

    data, meta = attachments_service.download_attachment(attachment_id)
    response = Response(data, mimetype=meta["content_type"])
    response.headers["Content-Disposition"] = (
        f'attachment; filename="{meta["original_filename"]}"'
    )
    return response


@bp.route("/chat/link_preview", methods=["GET", "POST"])
def link_preview_collection():
    if request.method == "GET":
        return jsonify({"hint": "POST a URL to fetch its preview."})
    return jsonify(link_preview_service.fetch_preview(request.get_json(silent=True) or {}))


@bp.route("/chat/channel_management", methods=["GET"])
def channel_management_root():
    return jsonify({"hint": "Use channel_id-scoped endpoints below."})


@bp.route(
    "/chat/channel_management/<int:channel_id>",
    methods=["GET", "PATCH"],
)
def channel_management_item(channel_id: int):
    if request.method == "GET":
        return jsonify(channel_management_service.channel_settings(channel_id))
    return jsonify(channel_management_service.update_channel(channel_id, request.get_json(silent=True) or {}))


@bp.route("/chat/channel_management/<int:channel_id>/rename", methods=["POST"])
def channel_management_rename(channel_id: int):
    return jsonify(channel_management_service.rename_channel(channel_id, request.get_json(silent=True) or {}))


@bp.route("/chat/channel_management/<int:channel_id>/archive", methods=["POST"])
def channel_management_archive(channel_id: int):
    return jsonify(channel_management_service.archive_channel(channel_id))


@bp.route("/chat/channel_management/<int:channel_id>/unarchive", methods=["POST"])
def channel_management_unarchive(channel_id: int):
    return jsonify(channel_management_service.unarchive_channel(channel_id))


@bp.route("/chat/channel_management/<int:workspace_id>/audit", methods=["GET"])
def channel_management_audit(workspace_id: int):
    return jsonify({"items": channel_management_service.list_audit_events(workspace_id)})


@bp.route("/chat/connection_and_message_handling", methods=["GET"])
def connection_root():
    return jsonify({"hint": "POST to /chat/connection_and_message_handling/connections to open a session."})


@bp.route(
    "/chat/connection_and_message_handling/connections",
    methods=["GET", "POST"],
)
def connection_collection():
    if request.method == "GET":
        return jsonify({"items": connection_service.list_connections()})
    return jsonify(connection_service.open_connection())


@bp.route(
    "/chat/connection_and_message_handling/connections/<connection_id>/events",
    methods=["POST"],
)
def connection_events(connection_id: str):
    return jsonify(connection_service.record_event(connection_id, request.get_json(silent=True) or {}))


@bp.route(
    "/chat/connection_and_message_handling/connections/<connection_id>",
    methods=["DELETE"],
)
def connection_close(connection_id: str):
    return jsonify(connection_service.close_connection(connection_id))


@bp.route("/chat/frontend_api_integration", methods=["GET", "POST"])
def frontend_api_integration_collection():
    if request.method == "GET":
        return jsonify({"items": frontend_api_service.list_states()})
    return jsonify(frontend_api_service.record_state(request.get_json(silent=True) or {}))


@bp.route("/chat/errors", methods=["GET", "POST"])
def errors_collection():
    if request.method == "GET":
        return jsonify({"items": errors_service.list_errors()})
    return jsonify(errors_service.report_error(request.get_json(silent=True) or {}))


_pages_bp = Blueprint("pages", __name__)


@_pages_bp.route("/")
def home():
    from flask import redirect, session, url_for

    from ..services.session_service import current_user

    if current_user() is None:
        return redirect(url_for("pages.signin_page"))
    return redirect(url_for("pages.dashboard_page"))


@_pages_bp.route("/signin")
def signin_page():
    from flask import render_template

    return render_template("signin.html")


@_pages_bp.route("/register")
def register_page():
    from flask import render_template

    return render_template("register.html")


@_pages_bp.route("/reset")
def reset_request_page():
    from flask import render_template

    return render_template("reset_request.html")


@_pages_bp.route("/reset/<token>")
def reset_perform_page(token: str):
    from flask import render_template

    return render_template("reset_perform.html", token=token)


@_pages_bp.route("/app")
def dashboard_page():
    from flask import redirect, render_template, url_for
    from ..services.session_service import current_user

    if current_user() is None:
        return redirect(url_for("pages.signin_page"))
    return render_template("dashboard.html")


@_pages_bp.route("/app/dm")
def dm_page():
    from flask import redirect, render_template, url_for
    from ..services.session_service import current_user

    if current_user() is None:
        return redirect(url_for("pages.signin_page"))
    return render_template("dm.html")


@_pages_bp.route("/app/admin")
def admin_page():
    from flask import redirect, render_template, url_for
    from ..services.session_service import current_user

    if current_user() is None or not current_user().is_admin:
        return redirect(url_for("pages.dashboard_page"))
    return render_template("admin.html")


def register_blueprints(app):
    app.register_blueprint(bp, url_prefix="/api")
    app.register_blueprint(_pages_bp)
