"""Application configuration loaded from environment variables."""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = BASE_DIR.parent

load_dotenv(PROJECT_ROOT / ".env")


def _resolve_db_uri(value: str) -> str:
    if value.startswith("sqlite:///"):
        relative = value.replace("sqlite:///", "", 1)
        path = Path(relative)
        if not path.is_absolute():
            path = (PROJECT_ROOT / relative).resolve()
        return f"sqlite:///{path.as_posix()}"
    return value


class Config:
    SECRET_KEY = os.environ.get("SECRET_KEY", "dev-only-secret-change-me")
    SQLALCHEMY_DATABASE_URI = _resolve_db_uri(
        os.environ.get("DATABASE_URI", "sqlite:///data/app.db")
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    SQLALCHEMY_ENGINE_OPTIONS = {"future": True}
    MAX_CONTENT_LENGTH = int(os.environ.get("MAX_CONTENT_LENGTH", str(25 * 1024 * 1024)))
    SESSION_COOKIE_HTTPONLY = True
    SESSION_COOKIE_SAMESITE = "Lax"
    SESSION_COOKIE_SECURE = False
    PERMANENT_SESSION_LIFETIME = 60 * 60 * 8  # 8 hours
    JSON_SORT_KEYS = False
    UPLOAD_FOLDER = str((PROJECT_ROOT / "data" / "uploads").resolve())
    EXPORT_FOLDER = str((PROJECT_ROOT / "data" / "exports").resolve())
    DATASET_RETENTION_DAYS = int(os.environ.get("DATASET_RETENTION_DAYS", "90"))
    MAX_DATASETS_PER_USER = int(os.environ.get("MAX_DATASETS_PER_USER", "25"))
    EXPOSE_DATA_SOURCES_TO_VIEWERS = os.environ.get(
        "EXPOSE_DATA_SOURCES_TO_VIEWERS", "0"
    ) == "1"
    HOST = os.environ.get("HOST", "127.0.0.1")
    PORT = int(os.environ.get("PORT", "5000"))
    FLASK_DEBUG = os.environ.get("FLASK_DEBUG", "1") == "1"
