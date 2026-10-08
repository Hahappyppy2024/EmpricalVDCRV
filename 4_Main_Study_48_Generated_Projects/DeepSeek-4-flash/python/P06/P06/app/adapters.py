"""Deterministic local adapters for external services.

Email, object storage and link-preview metadata are all backed by offline
local adapters so the application runs without paid services or accounts.
"""

from __future__ import annotations

import datetime as dt
import hashlib
import re
from pathlib import Path
from urllib.parse import urlparse

from flask import current_app


def _mail_dir() -> Path:
    return Path(current_app.config["MAILBOX_DIR"])


def _upload_dir() -> Path:
    return Path(current_app.config["UPLOAD_FOLDER"])


class LocalMailbox:
    """Writes every sent mail as a deterministic text file under MAILBOX_DIR."""

    def send(self, to: str, subject: str, body: str) -> str:
        directory = _mail_dir()
        directory.mkdir(parents=True, exist_ok=True)
        stamp = dt.datetime.now(dt.timezone.utc).strftime("%Y%m%d%H%M%S%f")
        safe_to = re.sub(r"[^A-Za-z0-9@._-]", "_", to)
        path = directory / f"{stamp}_{safe_to}.txt"
        content = (
            f"To: {to}\n"
            f"Subject: {subject}\n"
            f"Date: {dt.datetime.now(dt.timezone.utc).isoformat()}\n\n"
            f"{body}\n"
        )
        path.write_text(content, encoding="utf-8")
        return str(path)


mailbox = LocalMailbox()


class LocalStorage:
    """Stores uploaded bytes under UPLOAD_FOLDER with content-addressed names."""

    ALLOWED_EXTENSIONS = {
        ".txt", ".md", ".pdf", ".png", ".jpg", ".jpeg", ".gif", ".csv",
        ".json", ".log", ".zip", ".doc", ".docx", ".xlsx", ".py", ".js",
        ".html", ".css",
    }

    def save(self, filename: str, data: bytes) -> dict:
        directory = _upload_dir()
        directory.mkdir(parents=True, exist_ok=True)
        sha256 = hashlib.sha256(data).hexdigest()
        ext = Path(filename).suffix.lower()[:16]
        stored_name = f"{sha256[:16]}_{len(data)}{ext}"
        path = directory / stored_name
        if not path.exists():
            path.write_bytes(data)
        return {
            "stored_name": stored_name,
            "sha256": sha256,
            "size": len(data),
        }

    def open(self, stored_name: str) -> Path:
        return _upload_dir() / stored_name


storage = LocalStorage()


class LocalLinkPreviewFetcher:
    """Deterministic offline fetcher for URL metadata.

    Known URLs return stable fixture metadata; unknown URLs synthesize
    deterministic metadata from the parsed URL. No network is used.
    """

    KNOWN = {
        "https://example.com": {
            "title": "Example Domain",
            "description": "This domain is for use in illustrative examples in documents.",
            "site_name": "example.com",
            "image_url": None,
        },
        "https://example.org": {
            "title": "Example Org",
            "description": "Example organization site used for documentation and testing.",
            "site_name": "example.org",
            "image_url": None,
        },
        "https://www.python.org": {
            "title": "Welcome to Python.org",
            "description": "The official home of the Python Programming Language.",
            "site_name": "python.org",
            "image_url": "https://www.python.org/static/img/python-logo.png",
        },
        "https://github.com": {
            "title": "GitHub",
            "description": "Where the world builds software. GitHub is where over 100 million developers shape the future of software.",
            "site_name": "GitHub",
            "image_url": "https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png",
        },
        "https://zulip.com": {
            "title": "Zulip — Threaded Team Chat",
            "description": "The best team chat for focused work, with the productivity of email and the fun of chat.",
            "site_name": "Zulip",
            "image_url": None,
        },
    }

    def fetch(self, url: str) -> dict:
        if url in self.KNOWN:
            return dict(self.KNOWN[url])
        parsed = urlparse(url)
        host = parsed.netloc or parsed.scheme or "link"
        return {
            "title": f"Preview for {host}",
            "description": (
                f"Deterministic offline preview metadata for {url}."
            ),
            "site_name": host,
            "image_url": None,
        }


link_preview_fetcher = LocalLinkPreviewFetcher()
