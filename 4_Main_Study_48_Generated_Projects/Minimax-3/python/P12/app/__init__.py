"""Flask application factory for the Data Analytics Dashboard."""
from __future__ import annotations

import logging
from pathlib import Path

from flask import Flask, redirect, render_template, request, session, url_for

from .config import Config
from .extensions import db, sock
from .utils import ApiError, error_response, get_current_user


def create_app(config_object: type[Config] = Config) -> Flask:
    app = Flask(
        __name__,
        static_folder="static",
        template_folder="templates",
    )
    app.config.from_object(config_object)

    Path(app.config["UPLOAD_FOLDER"]).mkdir(parents=True, exist_ok=True)
    Path(app.config["EXPORT_FOLDER"]).mkdir(parents=True, exist_ok=True)

    db.init_app(app)
    sock.init_app(app)

    from . import ws_routes  # noqa: WPS433 - import after init

    ws_routes.register_sockets()

    # Register API blueprints
    from .api import (
        account_access,
        admin_operations,
        audit_and_lineage,
        calculated_columns,
        chart_builder,
        dashboard_sharing,
        data_preview,
        data_source_connections,
        dataset_catalog,
        dataset_upload,
        export,
        filter_builder,
    )

    app.register_blueprint(account_access.bp)
    app.register_blueprint(dataset_upload.bp)
    app.register_blueprint(dataset_catalog.bp)
    app.register_blueprint(data_preview.bp)
    app.register_blueprint(filter_builder.bp)
    app.register_blueprint(chart_builder.bp)
    app.register_blueprint(calculated_columns.bp)
    app.register_blueprint(dashboard_sharing.bp)
    app.register_blueprint(export.bp)
    app.register_blueprint(data_source_connections.bp)
    app.register_blueprint(audit_and_lineage.bp)
    app.register_blueprint(admin_operations.bp)

    # Server-rendered pages
    app.add_url_rule("/", view_func=render_index, methods=["GET"])
    app.add_url_rule("/login", view_func=render_login, methods=["GET"])
    app.add_url_rule("/signup", view_func=render_signup, methods=["GET"])
    app.add_url_rule("/dashboard", view_func=render_dashboard, methods=["GET"])
    app.add_url_rule("/datasets", view_func=render_datasets, methods=["GET"])
    app.add_url_rule("/datasets/upload", view_func=render_dataset_upload, methods=["GET"])
    app.add_url_rule("/datasets/<int:dataset_id>", view_func=render_dataset_detail, methods=["GET"])
    app.add_url_rule("/dashboards", view_func=render_dashboards, methods=["GET"])
    app.add_url_rule("/dashboards/<int:dataset_id>", view_func=render_dashboard_detail, methods=["GET"])
    app.add_url_rule("/admin", view_func=render_admin, methods=["GET"])
    app.add_url_rule("/audit", view_func=render_audit, methods=["GET"])
    app.add_url_rule("/data-sources", view_func=render_data_sources, methods=["GET"])

    @app.errorhandler(ApiError)
    def handle_api_error(exc: ApiError):
        return error_response(exc.message, status=exc.status, code=exc.code)

    @app.errorhandler(404)
    def handle_not_found(_exc):
        if request.path.startswith("/api/"):
            return error_response("Resource not found", status=404, code="not_found")
        return render_template("not_found.html"), 404

    @app.errorhandler(405)
    def handle_method_not_allowed(_exc):
        return error_response("Method not allowed", status=405, code="method_not_allowed")

    @app.errorhandler(500)
    def handle_internal_error(_exc):  # pragma: no cover - safety net
        app.logger.exception("Unhandled server error")
        return error_response("Internal server error", status=500, code="server_error")

    @app.context_processor
    def inject_globals():
        user = get_current_user()
        return {
            "current_user": user,
            "is_admin": user is not None and user.role == "admin",
            "is_analyst_or_admin": user is not None and user.role in {"analyst", "admin"},
        }

    if not app.debug:
        logging.basicConfig(level=logging.INFO)
    return app


def render_index():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return redirect(url_for("render_dashboard"))


def render_login():
    if get_current_user() is not None:
        return redirect(url_for("render_dashboard"))
    next_url = request.args.get("next") or url_for("render_dashboard")
    return render_template("login.html", next_url=next_url, signup_mode=False)


def render_signup():
    if get_current_user() is not None:
        return redirect(url_for("render_dashboard"))
    return render_template("login.html", next_url=url_for("render_dashboard"), signup_mode=True)


def render_dashboard():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return render_template("dashboard.html", user=user)


def render_datasets():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return render_template("datasets.html", user=user)


def render_dataset_upload():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    if user.role not in {"analyst", "admin"}:
        return render_template("forbidden.html"), 403
    return render_template("dataset_upload.html", user=user)


def render_dataset_detail(dataset_id: int):
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return render_template("dataset_detail.html", user=user, dataset_id=dataset_id)


def render_dashboards():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return render_template("dashboards.html", user=user)


def render_dashboard_detail(dataset_id: int):
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    return render_template("dashboard_detail.html", user=user, dataset_id=dataset_id)


def render_admin():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    if user.role != "admin":
        return render_template("forbidden.html"), 403
    return render_template("admin.html", user=user)


def render_audit():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    if user.role not in {"analyst", "admin"}:
        return render_template("forbidden.html"), 403
    return render_template("audit.html", user=user)


def render_data_sources():
    user = get_current_user()
    if user is None:
        return redirect(url_for("render_login"))
    if user.role not in {"analyst", "admin"}:
        return render_template("forbidden.html"), 403
    return render_template("data_sources.html", user=user)
