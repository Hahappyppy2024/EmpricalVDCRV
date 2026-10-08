"""Application factory for the Real-Time Team Chat System."""

from __future__ import annotations

import os
from pathlib import Path

from flask import Flask

from .config import Config
from .errors import register_error_handlers
from .extensions import db, sock


def _ensure_dirs(app: Flask) -> None:
    for folder in (
        Path(app.config["UPLOAD_FOLDER"]),
        Path(app.config["MAILBOX_DIR"]),
    ):
        folder.mkdir(parents=True, exist_ok=True)


def create_app(config_object=None) -> Flask:
    app = Flask(__name__)
    app.config.from_object(config_object or Config)
    app.config.from_prefixed_env()  # CHAT_* prefixed env vars override defaults

    _ensure_dirs(app)

    import app.models as _models  # noqa: F401  (register models on the metadata)

    # Register WebSocket routes before initializing the Sock extension.
    import app.blueprints.sockets as _sockets  # noqa: F401

    db.init_app(app)
    sock.init_app(app)

    from .auth import load_current_user

    app.before_request(load_current_user)

    @app.context_processor
    def inject_template_globals():
        from .auth import current_user

        return {"current_user": current_user()}

    from .realtime import RealtimeHub

    app.extensions["realtime"] = RealtimeHub()

    from .blueprints import ALL_BLUEPRINTS

    for blueprint in ALL_BLUEPRINTS:
        app.register_blueprint(blueprint)

    register_error_handlers(app)

    from .cli import register_cli

    register_cli(app)

    return app
