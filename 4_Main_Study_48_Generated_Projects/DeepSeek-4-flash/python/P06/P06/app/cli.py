"""Flask CLI commands for database initialization, reset and seeding."""

from __future__ import annotations

import click
from flask import current_app

from .extensions import db


def register_cli(app) -> None:
    @app.cli.command("init-db")
    def init_db() -> None:
        """Create all database tables."""
        db.create_all()
        click.echo("Database tables created.")

    @app.cli.command("drop-db")
    def drop_db() -> None:
        """Drop all database tables."""
        db.drop_all()
        click.echo("Database tables dropped.")

    @app.cli.command("seed")
    def seed_db() -> None:
        """Seed deterministic fixture data."""
        from .seeds import seed

        seed()
        click.echo("Seed data loaded.")

    @app.cli.command("reset-db")
    def reset_db() -> None:
        """Drop, recreate and seed the database."""
        db.drop_all()
        db.create_all()
        from .seeds import seed

        seed()
        click.echo("Database reset and seeded.")
