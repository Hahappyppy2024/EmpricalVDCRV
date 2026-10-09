from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")


class Config:
    SECRET_KEY = os.environ.get("FLASK_SECRET_KEY", "dev-secret-key-change-me")
    DEBUG = os.environ.get("FLASK_DEBUG", "0") == "1"

    SQLALCHEMY_DATABASE_URI = os.environ.get(
        "DATABASE_URL",
        f"sqlite:///{(BASE_DIR / 'data' / 'chat.db').as_posix()}",
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False

    STORAGE_DIR = Path(os.environ.get("STORAGE_DIR", BASE_DIR / "storage"))
    STORAGE_DIR.mkdir(parents=True, exist_ok=True)

    SESSION_LIFETIME = int(os.environ.get("SESSION_LIFETIME", 7 * 24 * 3600))

    HOST = os.environ.get("HOST", "0.0.0.0")
    PORT = int(os.environ.get("PORT", "5000"))

    BASE_DIR = BASE_DIR
