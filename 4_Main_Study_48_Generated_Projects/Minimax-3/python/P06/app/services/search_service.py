from __future__ import annotations

from datetime import datetime
from typing import Optional

from ..errors import ValidationError
from ..repositories import ChannelRepository, MessageRepository
from .session_service import require_user


def search_messages(payload: dict) -> dict:
    user = require_user()
    keyword = (payload.get("query") or "").strip()
    channel_id = payload.get("channel_id")
    start_str = payload.get("start")
    end_str = payload.get("end")
    if not keyword and not start_str and not end_str and not channel_id:
        return {"results": [], "message": "Provide a query, channel, or date range."}
    start = _parse_dt(start_str)
    end = _parse_dt(end_str)
    if start and end and start > end:
        raise ValidationError("Start date cannot be after end date.")
    cid: Optional[int] = None
    if channel_id:
        try:
            cid = int(channel_id)
        except (TypeError, ValueError):
            raise ValidationError("channel_id must be an integer.")
        channel = ChannelRepository.by_id(cid)
        if channel is None:
            raise ValidationError("Channel does not exist.")
    raw_limit = payload.get("limit", 50)
    if raw_limit in (None, ""):
        limit = 50
    else:
        try:
            limit = int(raw_limit)
        except (TypeError, ValueError):
            raise ValidationError("limit must be an integer.")
    limit = max(1, min(limit, 100))
    results = MessageRepository.search(
        user_id=user.id,
        keyword=keyword or None,
        start=start,
        end=end,
        channel_id=cid,
        limit=limit,
    )
    return {
        "query": keyword,
        "count": len(results),
        "results": [_serialize_message(m) for m in results if not m.deleted],
    }


def _parse_dt(value):
    if not value:
        return None
    try:
        return datetime.fromisoformat(value.replace("Z", "+00:00"))
    except ValueError:
        raise ValidationError(f"Invalid datetime: {value}")


def _serialize_message(m) -> dict:
    return {
        "id": m.id,
        "channel_id": m.channel_id,
        "sender_id": m.sender_id,
        "body": m.body if not m.deleted else "(deleted)",
        "topic": m.topic,
        "edited": m.edited,
        "delivery_state": m.delivery_state,
        "created_at": m.created_at.isoformat() if m.created_at else None,
    }
