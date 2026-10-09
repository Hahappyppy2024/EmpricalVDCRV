from __future__ import annotations

import secrets
from pathlib import Path
from typing import Optional

from flask import g, request
from sqlalchemy import create_engine
from sqlalchemy.orm import DeclarativeBase, scoped_session, sessionmaker

from .config import Config


class Base(DeclarativeBase):
    pass


_engine = create_engine(
    Config.SQLALCHEMY_DATABASE_URI,
    future=True,
    echo=False,
)
SessionLocal = scoped_session(
    sessionmaker(bind=_engine, autoflush=False, autocommit=False, future=True)
)


def init_engine() -> None:
    Path(Config.SQLALCHEMY_DATABASE_URI.replace("sqlite:///", "")).parent.mkdir(
        parents=True, exist_ok=True
    )


def get_session():
    return SessionLocal()


def remove_session(exc=None) -> None:
    SessionLocal.remove()


def install_flask_hooks(app) -> None:
    app.teardown_appcontext(remove_session)


def current_session():
    if "db_session" not in g:
        g.db_session = SessionLocal()
    return g.db_session


def new_request_id() -> str:
    return secrets.token_hex(8)


def client_ip() -> str:
    return request.remote_addr or "0.0.0.0"
