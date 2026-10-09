from __future__ import annotations

from datetime import datetime, timedelta, timezone

from flask import Flask, render_template, session

from .config import Config
from .controllers import register_blueprints
from .db import init_engine, install_flask_hooks
from .errors import ServiceError
from .models import (
    Attachment,
    AuditEvent,
    Channel,
    ConnectionState,
    DirectMessage,
    ErrorLog,
    FrontendState,
    Invitation,
    LinkPreview,
    Membership,
    Message,
    SessionRecord,
    User,
    Workspace,
)
from .db import Base, _engine
from .websocket import register_websocket


def create_app() -> Flask:
    app = Flask(__name__, static_folder="static", template_folder="templates")
    app.config.from_object(Config)
    app.permanent_session_lifetime = timedelta(seconds=Config.SESSION_LIFETIME)
    app.config["MAX_CONTENT_LENGTH"] = 20 * 1024 * 1024

    init_engine()
    install_flask_hooks(app)
    Base.metadata.create_all(bind=_engine)

    @app.errorhandler(ServiceError)
    def _service_error(exc: ServiceError):
        from flask import jsonify

        response = jsonify({"status": "error", "code": exc.code, "message": exc.message})
        response.status_code = exc.http_status
        return response

    @app.errorhandler(404)
    def _not_found(_exc):
        from flask import jsonify

        return jsonify({"status": "error", "code": "not_found", "message": "Route not found."}), 404

    @app.errorhandler(405)
    def _bad_method(_exc):
        from flask import jsonify

        return jsonify({"status": "error", "code": "method_not_allowed", "message": "Method not allowed for this route."}), 405

    @app.context_processor
    def _inject_globals():
        from .services.session_service import current_user

        user = current_user()
        return {"current_user": user, "now": datetime.now(timezone.utc)}

    register_blueprints(app)
    register_websocket(app)

    @app.route("/healthz")
    def healthz():
        return {"status": "ok", "service": "real_time_team_chat_system"}

    return app
