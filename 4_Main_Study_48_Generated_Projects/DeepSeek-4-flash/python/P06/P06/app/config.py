import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent


def _bool(value):
    return str(value).strip().lower() in ("1", "true", "yes", "on")


class Config:
    SECRET_KEY = os.environ.get("SECRET_KEY", "dev-secret-change-me")

    DATABASE_URL = os.environ.get(
        "DATABASE_URL", f"sqlite:///{BASE_DIR / 'instance' / 'chat.db'}"
    )
    SQLALCHEMY_DATABASE_URI = DATABASE_URL
    SQLALCHEMY_TRACK_MODIFICATIONS = False

    SESSION_COOKIE_NAME = os.environ.get("SESSION_COOKIE_NAME", "chat_session")
    SESSION_TTL_SECONDS = int(os.environ.get("SESSION_TTL_SECONDS", "604800"))
    COOKIE_SECURE = _bool(os.environ.get("COOKIE_SECURE", "false"))

    UPLOAD_FOLDER = os.environ.get(
        "UPLOAD_FOLDER", str(BASE_DIR / "instance" / "uploads")
    )
    MAILBOX_DIR = os.environ.get(
        "MAILBOX_DIR", str(BASE_DIR / "instance" / "mailbox")
    )
    MAX_UPLOAD_BYTES = int(os.environ.get("MAX_UPLOAD_BYTES", "16777216"))
    MAX_CONTENT_LENGTH = MAX_UPLOAD_BYTES

    PUBLIC_BASE_URL = os.environ.get("PUBLIC_BASE_URL", "http://127.0.0.1:5000")

    JSON_SORT_KEYS = False

    SOCK_SERVER_OPTIONS = {"ping_interval": 25}
