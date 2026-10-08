"""Application factory for the P12 Data Analytics Dashboard."""
import os

from flask import Flask, g

from .config import Config
from .extensions import Base, init_db_engine
from . import extensions as _ext


def create_app(config_object=None):
    app = Flask(__name__)
    app.config.from_object(Config if config_object is None else config_object)

    os.makedirs(os.path.dirname(app.config["DATABASE_PATH"]), exist_ok=True)
    init_db_engine(app.config["DATABASE_PATH"])

    from . import models  # noqa: F401  (register all models)

    Base.metadata.create_all(_ext.SessionLocal().get_bind())

    from .pages import pages_bp
    from .api import api_bp

    app.register_blueprint(pages_bp)
    app.register_blueprint(api_bp)

    @app.teardown_appcontext
    def close_session(exc):  # noqa: ARG001
        session = g.pop("db", None)
        if session is not None:
            session.close()

    _register_cli(app)
    return app


def _register_cli(app):
    import click

    from . import seed as seed_module
    from .data_access import Repository
    from .extensions import SessionLocal

    @app.cli.command("init-db")
    def init_db_command():
        """Reset the SQLite database and load deterministic seed fixtures."""
        engine = _ext.SessionLocal().get_bind()
        Base.metadata.drop_all(engine)
        Base.metadata.create_all(engine)
        session = SessionLocal()
        try:
            seed_module.seed(Repository(session))
            session.commit()
        except Exception as exc:  # noqa: BLE001
            session.rollback()
            click.echo(f"Seed failed: {exc}")
            raise
        finally:
            session.close()
        click.echo("Database reset and seeded successfully.")
