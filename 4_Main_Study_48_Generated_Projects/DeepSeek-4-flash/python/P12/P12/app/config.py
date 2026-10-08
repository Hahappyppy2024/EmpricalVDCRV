"""Application configuration loaded from environment variables."""
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent


class Config:
    """Central configuration. Every value can be overridden via environment."""

    SECRET_KEY = os.environ.get("FLASK_SECRET_KEY", "dev-secret-change-me")
    DATABASE_PATH = os.environ.get(
        "DATABASE_PATH", str(BASE_DIR / "data" / "analytics.db")
    )
    UPLOAD_DIR = os.environ.get("UPLOAD_DIR", str(BASE_DIR / "data" / "uploads"))
    MAX_UPLOAD_MB = int(os.environ.get("MAX_UPLOAD_MB", "10"))
    PORT = int(os.environ.get("PORT", "8000"))
    SESSION_COOKIE_NAME = "session"  # Flask's signed session (flash messages)
    AUTH_COOKIE_NAME = "dash_session_token"  # our persistent DB-backed session
    SESSION_LIFETIME_DAYS = 7
