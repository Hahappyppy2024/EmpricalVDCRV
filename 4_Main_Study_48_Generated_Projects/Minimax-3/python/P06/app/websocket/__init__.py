from __future__ import annotations

import json
import secrets
import threading
import time
from typing import Any, Optional

from flask_sock import Sock

from ..db import SessionLocal
from ..models import User
from ..repositories import SessionRepository

sock = Sock()
_connections: dict[str, dict[str, Any]] = {}
_lock = threading.Lock()


def _resolve_user_from_cookie(cookie_header: str) -> Optional[User]:
    if not cookie_header:
        return None
    pairs = [p.strip() for p in cookie_header.split(";")]
    sid = None
    for pair in pairs:
        if pair.startswith("session="):
            sid = pair.split("=", 1)[1]
            break
    if not sid:
        return None
    record = SessionRepository.by_session_id(sid)
    if record is None or record.revoked:
        return None
    with SessionLocal() as session:
        return session.get(User, record.user_id)


def register_websocket(app):
    sock.init_app(app)

    @sock.route("/ws/chat")
    def ws_chat(ws):
        cookie = ws.headers.get("Cookie", "") if hasattr(ws, "headers") else ""
        user = _resolve_user_from_cookie(cookie)
        connection_id = secrets.token_hex(8)
        if user is None:
            ws.send(json.dumps({"type": "error", "data": {"code": "auth_error", "message": "Sign in required."}}))
            ws.close()
            return
        with _lock:
            _connections[connection_id] = {
                "ws": ws,
                "user_id": user.id,
                "username": user.username,
                "subscriptions": set(),
                "last_event_id": "",
            }
        try:
            ws.send(
                json.dumps(
                    {
                        "type": "welcome",
                        "data": {
                            "connection_id": connection_id,
                            "user_id": user.id,
                            "username": user.username,
                        },
                    }
                )
            )
            while True:
                msg = ws.receive(timeout=60)
                if msg is None:
                    ws.send(json.dumps({"type": "heartbeat", "data": {"ts": int(time.time())}}))
                    continue
                try:
                    payload = json.loads(msg)
                except json.JSONDecodeError:
                    ws.send(json.dumps({"type": "error", "data": {"code": "bad_message", "message": "Invalid JSON."}}))
                    continue
                _handle_payload(connection_id, payload)
        except Exception:
            pass
        finally:
            with _lock:
                _connections.pop(connection_id, None)

    return _connections


def _handle_payload(connection_id: str, payload: dict[str, Any]) -> None:
    ptype = payload.get("type")
    data = payload.get("data") or {}
    with _lock:
        record = _connections.get(connection_id)
        if record is None:
            return
        ws = record["ws"]
        user_id = record["user_id"]
    if ptype == "ping":
        ws.send(json.dumps({"type": "pong", "data": {"ts": int(time.time())}}))
    elif ptype == "subscribe":
        channel = data.get("channel")
        if channel:
            with _lock:
                record["subscriptions"].add(channel)
            ws.send(json.dumps({"type": "subscribed", "data": {"channel": channel}}))
    elif ptype == "unsubscribe":
        channel = data.get("channel")
        if channel:
            with _lock:
                record["subscriptions"].discard(channel)
            ws.send(json.dumps({"type": "unsubscribed", "data": {"channel": channel}}))
    elif ptype == "message":
        channel_id = data.get("channel_id")
        body = (data.get("body") or "").strip()
        client_id = data.get("client_id") or secrets.token_hex(6)
        if not channel_id or not body:
            ws.send(
                json.dumps(
                    {
                        "type": "error",
                        "data": {"code": "validation_error", "message": "channel_id and body are required."},
                    }
                )
            )
            return
        from ..services.messaging_service import send_message

        try:
            sent = send_message(int(channel_id), {"body": body, "client_id": client_id, "topic": data.get("topic")})
        except Exception as exc:
            ws.send(json.dumps({"type": "error", "data": {"code": "send_failed", "message": str(exc)}}))
            return
        envelope = {
            "type": "message.created",
            "data": {
                "channel_id": int(channel_id),
                "message": sent,
                "sender_id": user_id,
            },
        }
        broadcast_to_channel(int(channel_id), envelope)
    elif ptype == "ack":
        last_event_id = data.get("last_event_id") or ""
        with _lock:
            record["last_event_id"] = last_event_id
        ws.send(json.dumps({"type": "ack", "data": {"last_event_id": last_event_id}}))
    else:
        ws.send(json.dumps({"type": "error", "data": {"code": "unknown_type", "message": f"Unknown type: {ptype}"}}))


def broadcast_to_channel(channel_id: int, envelope: dict[str, Any]) -> None:
    payload = json.dumps(envelope)
    with _lock:
        for cid, record in list(_connections.items()):
            subs = record.get("subscriptions") or set()
            if f"channel:{channel_id}" in subs:
                try:
                    record["ws"].send(payload)
                except Exception:
                    pass


def broadcast_global(envelope: dict[str, Any]) -> None:
    payload = json.dumps(envelope)
    with _lock:
        for cid, record in list(_connections.items()):
            try:
                record["ws"].send(payload)
            except Exception:
                pass
