from __future__ import annotations

from typing import Optional

from ..errors import NotFoundError, ValidationError
from ..repositories import DirectMessageRepository, UserRepository
from .session_service import require_user


def _thread_key(a: int, b: int) -> str:
    lo, hi = sorted([a, b])
    return f"dm:{lo}-{hi}"


def list_threads() -> list[dict]:
    user = require_user()
    all_dms = DirectMessageRepository.threads_for_user(user.id)
    threads: dict[str, dict] = {}
    for dm in all_dms:
        other_id = dm.recipient_id if dm.sender_id == user.id else dm.sender_id
        other = UserRepository.by_id(other_id)
        if other is None:
            continue
        if dm.thread_key in threads:
            entry = threads[dm.thread_key]
            entry["last_body"] = dm.body
            entry["last_created_at"] = dm.created_at.isoformat()
            entry["unread_count"] += 0 if dm.read or dm.sender_id == user.id else 1
        else:
            threads[dm.thread_key] = {
                "thread_key": dm.thread_key,
                "other_user": {
                    "id": other.id,
                    "username": other.username,
                    "display_name": other.display_name,
                    "avatar_seed": other.avatar_seed,
                },
                "last_body": dm.body,
                "last_created_at": dm.created_at.isoformat(),
                "unread_count": 0 if dm.read or dm.sender_id == user.id else 1,
            }
    return sorted(
        list(threads.values()),
        key=lambda t: t["last_created_at"] or "",
        reverse=True,
    )


def open_thread(other_user_id: int) -> dict:
    user = require_user()
    other = UserRepository.by_id(other_user_id)
    if other is None or other.id == user.id:
        raise ValidationError("Invalid recipient.")
    key = _thread_key(user.id, other.id)
    messages = DirectMessageRepository.by_thread(key)
    DirectMessageRepository.mark_read(key, user.id)
    unread = DirectMessageRepository.unread_count(user.id)
    return {
        "thread_key": key,
        "other_user": {
            "id": other.id,
            "username": other.username,
            "display_name": other.display_name,
            "avatar_seed": other.avatar_seed,
        },
        "messages": [_serialize_dm(m, current_user_id=user.id) for m in messages],
        "unread_total": unread,
    }


def send_dm(other_user_id: int, payload: dict) -> dict:
    user = require_user()
    other = UserRepository.by_id(other_user_id)
    if other is None or other.id == user.id:
        raise ValidationError("Invalid recipient.")
    body = (payload.get("body") or "").strip()
    if not body:
        raise ValidationError("Message body is required.")
    if len(body) > 4000:
        raise ValidationError("Message body exceeds 4000 characters.")
    key = _thread_key(user.id, other.id)
    dm = DirectMessageRepository.create(
        thread_key=key,
        sender_id=user.id,
        recipient_id=other.id,
        body=body,
    )
    return _serialize_dm(dm, current_user_id=user.id)


def mark_thread_read(thread_key: str) -> dict:
    user = require_user()
    expected = _thread_key(user.id, _other_from_key(thread_key, user.id))
    if thread_key != expected:
        raise NotFoundError("Thread not found.")
    count = DirectMessageRepository.mark_read(thread_key, user.id)
    return {"thread_key": thread_key, "marked_read": count}


def _other_from_key(thread_key: str, user_id: int) -> int:
    if not thread_key.startswith("dm:"):
        raise NotFoundError("Invalid thread key.")
    try:
        lo, hi = thread_key[3:].split("-", 1)
        lo_i, hi_i = int(lo), int(hi)
    except ValueError:
        raise NotFoundError("Invalid thread key.")
    if user_id == lo_i:
        return hi_i
    if user_id == hi_i:
        return lo_i
    raise NotFoundError("Thread does not involve current user.")


def _serialize_dm(dm, *, current_user_id: int) -> dict:
    return {
        "id": dm.id,
        "thread_key": dm.thread_key,
        "sender_id": dm.sender_id,
        "recipient_id": dm.recipient_id,
        "body": dm.body,
        "read": dm.read,
        "created_at": dm.created_at.isoformat() if dm.created_at else None,
        "outgoing": dm.sender_id == current_user_id,
    }
