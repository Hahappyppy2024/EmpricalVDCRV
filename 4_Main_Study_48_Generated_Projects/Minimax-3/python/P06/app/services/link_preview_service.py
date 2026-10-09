from __future__ import annotations

import re
from html.parser import HTMLParser
from typing import Optional
from urllib.parse import urlparse

from ..errors import ValidationError
from ..repositories import LinkPreviewRepository
from .session_service import require_user

URL_RE = re.compile(r"^https?://[^\s]+$", re.IGNORECASE)

KOWN_TITLES = {
    "https://example.com/": ("Example Domain", "Reserved for documentation examples.", "Example"),
    "https://example.org/": ("Example Org", "Reserved organization documentation.", "Example"),
    "https://docs.python.org/": ("Python Docs", "Official Python programming language documentation.", "Python"),
}


class _MetaExtractor(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.title: str = ""
        self.description: str = ""
        self.image: Optional[str] = None
        self.site_name: Optional[str] = None
        self._capture_title = False

    def handle_starttag(self, tag, attrs):
        attrs_d = {k.lower(): v for k, v in attrs}
        if tag.lower() == "title":
            self._capture_title = True
        elif tag.lower() == "meta":
            name = (attrs_d.get("name") or attrs_d.get("property") or "").lower()
            content = attrs_d.get("content") or ""
            if name == "description":
                self.description = content
            elif name == "og:title":
                self.title = content
            elif name == "og:description" and not self.description:
                self.description = content
            elif name == "og:image":
                self.image = content
            elif name == "og:site_name":
                self.site_name = content

    def handle_data(self, data):
        if self._capture_title:
            self.title = (self.title + " " + data.strip()).strip()


def fetch_preview(payload: dict) -> dict:
    user = require_user()
    url = (payload.get("url") or "").strip()
    if not URL_RE.match(url):
        raise ValidationError("A fully qualified http(s) URL is required.")
    parsed = urlparse(url)
    if not parsed.netloc:
        raise ValidationError("URL must contain a network location.")
    existing = LinkPreviewRepository.by_url(url)
    if existing is not None and existing.requested_by == user.id:
        return _serialize_preview(existing)
    title, description, site = KOWN_TITLES.get(url, ("", "", parsed.netloc))
    if not title:
        title = parsed.netloc
        description = f"Deterministic preview for {parsed.netloc} path {parsed.path or '/'}"
        site = parsed.netloc
    preview = LinkPreviewRepository.create(
        url=url,
        title=title,
        description=description,
        image_url=None,
        site_name=site,
        requested_by=user.id,
    )
    return _serialize_preview(preview)


def _serialize_preview(p) -> dict:
    return {
        "id": p.id,
        "url": p.url,
        "title": p.title,
        "description": p.description,
        "image_url": p.image_url,
        "site_name": p.site_name,
        "requested_by": p.requested_by,
        "created_at": p.created_at.isoformat() if p.created_at else None,
    }
