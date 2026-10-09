"""WebSocket routes for collaborative preview events (DATA-04, DATA-06).

Uses Flask-Sock; if the optional dependency is unavailable the routes are not
registered so the application continues to work over plain HTTP.
"""
from __future__ import annotations

import json
import time

from flask import current_app

from .extensions import sock
from .utils import get_current_user

try:  # pragma: no cover - websocket runtime guard
    from simple_websocket import ConnectionClosed
except Exception:  # pragma: no cover
    ConnectionClosed = Exception


def register_sockets() -> None:
    @sock.route("/ws/data_preview")
    def data_preview_socket(ws):
        user = get_current_user()
        if user is None:
            ws.send(json.dumps({"error": {"code": "unauthenticated", "message": "Sign in required"}}))
            ws.close()
            return
        ws.send(json.dumps({"event": "ready", "channel": "data_preview"}))
        last_ping = time.time()
        while True:
            try:
                msg = ws.receive(timeout=30)
            except ConnectionClosed:
                return
            except Exception:  # pragma: no cover - defensive
                return
            now = time.time()
            if msg is None:
                if now - last_ping > 60:
                    ws.send(json.dumps({"event": "ping"}))
                    last_ping = now
                continue
            try:
                payload = json.loads(msg)
            except json.JSONDecodeError:
                ws.send(json.dumps({"error": {"code": "bad_request", "message": "Invalid JSON"}}))
                continue
            event = payload.get("event") or "echo"
            ws.send(json.dumps({"event": "ack", "source": event, "user": user.username}))

    @sock.route("/ws/chart_builder")
    def chart_builder_socket(ws):
        user = get_current_user()
        if user is None or user.role not in {"analyst", "admin"}:
            ws.send(json.dumps({"error": {"code": "forbidden", "message": "Analyst or admin required"}}))
            ws.close()
            return
        ws.send(json.dumps({"event": "ready", "channel": "chart_builder"}))
        last_ping = time.time()
        while True:
            try:
                msg = ws.receive(timeout=30)
            except ConnectionClosed:
                return
            except Exception:  # pragma: no cover - defensive
                return
            now = time.time()
            if msg is None:
                if now - last_ping > 60:
                    ws.send(json.dumps({"event": "ping"}))
                    last_ping = now
                continue
            try:
                payload = json.loads(msg)
            except json.JSONDecodeError:
                ws.send(json.dumps({"error": {"code": "bad_request", "message": "Invalid JSON"}}))
                continue
            ws.send(
                json.dumps(
                    {
                        "event": "broadcast",
                        "source": payload.get("event"),
                        "channel": "chart_builder",
                        "user": user.username,
                    }
                )
            )
