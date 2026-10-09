from __future__ import annotations

import hashlib
import re
import secrets
from pathlib import Path
from typing import Any

from flask import current_app

from ..config import Config
from ..errors import ForbiddenError, NotFoundError, ValidationError
from ..repositories import AttachmentRepository
from .session_service import require_user

MAX_FILE_BYTES = 10 * 1024 * 1024
ALLOWED_EXT = {"txt", "md", "json", "csv", "log", "png", "jpg", "jpeg", "gif", "pdf"}


def upload_attachment(file_storage, message_id: int | None = None) -> dict:
    user = require_user()
    if file_storage is None or file_storage.filename == "":
        raise ValidationError("File is required.")
    original = file_storage.filename
    ext = original.rsplit(".", 1)[-1].lower() if "." in original else ""
    if ext not in ALLOWED_EXT:
        raise ValidationError(
            f"File type '{ext}' is not allowed. Allowed: {sorted(ALLOWED_EXT)}"
        )
    payload = file_storage.read()
    if not payload:
        raise ValidationError("Empty file.")
    if len(payload) > MAX_FILE_BYTES:
        raise ValidationError("File exceeds maximum size of 10 MB.")
    digest = hashlib.sha256(payload).hexdigest()[:12]
    stored_name = f"{user.id}_{digest}_{secrets.token_hex(4)}.{ext}"
    storage = Path(Config.STORAGE_DIR)
    storage.mkdir(parents=True, exist_ok=True)
    storage.joinpath(stored_name).write_bytes(payload)
    attachment = AttachmentRepository.create(
        stored_filename=stored_name,
        original_filename=original,
        content_type=file_storage.mimetype or "application/octet-stream",
        size_bytes=len(payload),
        owner_id=user.id,
        message_id=message_id,
    )
    return {
        "id": attachment.id,
        "original_filename": attachment.original_filename,
        "stored_filename": attachment.stored_filename,
        "content_type": attachment.content_type,
        "size_bytes": attachment.size_bytes,
        "owner_id": attachment.owner_id,
        "message_id": attachment.message_id,
        "created_at": attachment.created_at.isoformat(),
    }


def list_attachments() -> list[dict]:
    user = require_user()
    items = AttachmentRepository.list_for_user(user.id)
    return [_serialize_attachment(a) for a in items]


def download_attachment(attachment_id: int) -> tuple[bytes, dict]:
    user = require_user()
    attachment = AttachmentRepository.by_id(attachment_id)
    if attachment is None:
        raise NotFoundError("Attachment not found.")
    if attachment.owner_id != user.id and not user.is_admin:
        raise ForbiddenError("Cannot download another user's attachment.")
    storage = Path(Config.STORAGE_DIR) / attachment.stored_filename
    if not storage.exists():
        raise NotFoundError("Stored file is missing.")
    data = storage.read_bytes()
    return data, _serialize_attachment(attachment)


def _serialize_attachment(a) -> dict[str, Any]:
    return {
        "id": a.id,
        "original_filename": a.original_filename,
        "stored_filename": a.stored_filename,
        "content_type": a.content_type,
        "size_bytes": a.size_bytes,
        "owner_id": a.owner_id,
        "message_id": a.message_id,
        "created_at": a.created_at.isoformat() if a.created_at else None,
    }
