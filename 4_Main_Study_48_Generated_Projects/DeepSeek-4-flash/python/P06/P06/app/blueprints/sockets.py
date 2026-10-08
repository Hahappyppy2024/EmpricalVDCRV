"""WebSocket routes for realtime chat (CHAT-04, CHAT-06, CHAT-10, CHAT-11).

Flask-Sock 0.1.0 registers every ``@sock.route`` handler onto an internal
blueprint; ``sock.init_app(app)`` then registers that blueprint. This module
must therefore be imported (so the routes are registered) before
``sock.init_app`` runs inside the app factory.
"""

from __future__ import annotations

import json

from flask import current_app, request
from flask_sock import ConnectionClosed

from ..errors import AppError
from ..extensions import sock
from ..realtime import RealtimeHub
from ..repositories import session_repo
from ..services import (
    connection_service,
    dm_service,
    messaging_service,
)


def _get_hub() -> RealtimeHub:
    return current_app.extensions["realtime"]


def _authenticated_user(ws):
    token = request.cookies.get(current_app.config["SESSION_COOKIE_NAME"])
    if not token:
        _send_error(ws, "unauthorized", "Not authenticated")
        _close(ws)
        return None
    auth_session = session_repo.get_active(token)
    if auth_session is None or auth_session.user is None or auth_session.user.status != "active":
        _send_error(ws, "unauthorized", "Session invalid or expired")
        _close(ws)
        return None
    return auth_session.user


def _send(ws, payload: dict) -> None:
    try:
        ws.send(json.dumps(payload))
    except Exception:
        pass


def _send_error(ws, code: str, message: str, client_msg_id=None) -> None:
    payload = {"type": "error", "code": code, "message": message}
    if client_msg_id:
        payload["client_msg_id"] = client_msg_id
    _send(ws, payload)


def _close(ws) -> None:
    try:
        ws.close()
    except Exception:
        pass


@sock.route("/ws/chat")
def ws_chat(ws):
    user = _authenticated_user(ws)
    if user is None:
        return
    hub = _get_hub()
    hub.add_client(ws, user.id)
    connection_service.record_event(
        user, "connect", session_id="", payload={"user": user.id}
    )
    hub.publish_to_user(
        user.id, {"type": "presence", "online": sorted(hub.online_user_ids())}
    )
    _send(ws, {"type": "hello", "user": user.to_dict(), "session_id": ""})
    try:
        while True:
            try:
                raw = ws.receive()
            except ConnectionClosed:
                break
            if raw is None:
                break
            try:
                payload = json.loads(raw)
            except (TypeError, ValueError):
                _send_error(ws, "invalid_json", "Message is not valid JSON")
                continue
            _handle_message(ws, user, payload)
    finally:
        hub.remove_client(ws)
        connection_service.record_event(
            user, "disconnect", session_id="", payload={"user": user.id}
        )


def _handle_message(ws, user, payload: dict) -> None:
    hub = _get_hub()
    message_type = payload.get("type")
    try:
        if message_type == "subscribe":
            workspace_slug = payload.get("workspace_slug")
            if workspace_slug:
                hub.subscribe(ws, f"ws:{workspace_slug}")
            for channel_id in payload.get("channels", []) or []:
                hub.subscribe(ws, f"ch:{int(channel_id)}")
            for thread_id in payload.get("threads", []) or []:
                hub.subscribe(ws, f"dm:{int(thread_id)}")
            hub.subscribe(ws, f"user:{user.id}")
            _send(ws, {"type": "subscribed", "workspace_slug": workspace_slug})
        elif message_type == "ping":
            _send(ws, {"type": "pong", "t": payload.get("t")})
        elif message_type == "presence":
            _send(ws, {"type": "presence", "online": sorted(hub.online_user_ids())})
        elif message_type == "message":
            result = messaging_service.send(
                user,
                payload.get("slug", ""),
                int(payload.get("channel_id") or 0),
                payload.get("body", ""),
                client_msg_id=payload.get("client_msg_id"),
                topic_id=int(payload.get("topic_id") or 0) or None,
            )
            _send(ws, {"type": "ack", **result})
        elif message_type == "edit":
            result = messaging_service.edit(
                user, payload.get("slug", ""), int(payload.get("message_id", 0)), payload.get("body", "")
            )
            _send(ws, {"type": "ack", "ok": True, **result})
        elif message_type == "delete":
            result = messaging_service.delete(
                user, payload.get("slug", ""), int(payload.get("message_id", 0))
            )
            _send(ws, {"type": "ack", "ok": True, **result})
        elif message_type == "dm":
            result = dm_service.send(
                user,
                payload.get("recipient", ""),
                payload.get("body", ""),
                client_msg_id=payload.get("client_msg_id"),
            )
            _send(ws, {"type": "ack", **result})
        elif message_type == "dm_read":
            result = dm_service.mark_read(user, int(payload.get("thread_id", 0)))
            _send(ws, {"type": "ack", "ok": True, **result})
        elif message_type == "read":
            message_id = payload.get("message_id")
            if message_id is not None:
                connection_service.record_event(user, "read", message_id=int(message_id))
                _send(ws, {"type": "ack", "ok": True, "message_id": int(message_id)})
        elif message_type == "typing":
            channel_id = payload.get("channel_id")
            thread_id = payload.get("thread_id")
            if channel_id is not None:
                hub.publish(
                    f"ch:{int(channel_id)}",
                    {"type": "typing", "user": user.to_dict(), "channel_id": int(channel_id)},
                    exclude=ws,
                )
            if thread_id is not None:
                hub.publish(
                    f"dm:{int(thread_id)}",
                    {"type": "typing", "user": user.to_dict(), "thread_id": int(thread_id)},
                    exclude=ws,
                )
        elif message_type == "reconnect":
            connection_service.record_event(
                user, "reconnect", session_id=payload.get("session_id", "")
            )
            _send(ws, {"type": "ack", "ok": True})
        elif message_type == "duplicate":
            connection_service.record_event(
                user,
                "duplicate",
                session_id=payload.get("session_id", ""),
                message_id=int(payload.get("message_id") or 0) or None,
            )
            _send(ws, {"type": "ack", "ok": True})
        else:
            _send_error(ws, "unknown_type", f"Unknown message type: {message_type}")
    except AppError as error:
        _send_error(ws, error.code, error.message, payload.get("client_msg_id"))
    except (TypeError, ValueError):
        _send_error(ws, "invalid_payload", "Invalid payload")
