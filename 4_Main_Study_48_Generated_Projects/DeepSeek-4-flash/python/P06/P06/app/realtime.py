"""In-process realtime hub used by Flask-Sock WebSocket connections."""

from __future__ import annotations

import json
import threading
from collections import defaultdict


class RealtimeHub:
    """Tracks live WebSocket clients and their subscriptions.

    Subscription keys:
      ws:{workspace_slug}           all events for a workspace
      ch:{channel_id}               channel messages/edits/deletes
      dm:{thread_id}                direct messages in a thread
      user:{user_id}                events targeted at a single user (DM typing)
    """

    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._clients: dict = {}
        self._subs: dict[str, set] = defaultdict(set)
        self._send_locks: dict = {}

    def add_client(self, ws, user_id: int, session_id: str = "") -> None:
        with self._lock:
            self._clients[ws] = {
                "user_id": user_id,
                "session_id": session_id,
                "subs": set(),
            }

    def remove_client(self, ws) -> None:
        with self._lock:
            entry = self._clients.pop(ws, None)
            if entry:
                for key in entry["subs"]:
                    bucket = self._subs.get(key)
                    if bucket:
                        bucket.discard(ws)
            self._send_locks.pop(ws, None)

    def client_user(self, ws) -> int | None:
        with self._lock:
            entry = self._clients.get(ws)
            return entry["user_id"] if entry else None

    def subscribe(self, ws, key: str) -> None:
        with self._lock:
            if ws in self._clients:
                self._clients[ws]["subs"].add(key)
                self._subs[key].add(ws)

    def unsubscribe(self, ws, key: str) -> None:
        with self._lock:
            if ws in self._clients:
                self._clients[ws]["subs"].discard(key)
                self._subs[key].discard(ws)

    def online_user_ids(self) -> set[int]:
        with self._lock:
            return {entry["user_id"] for entry in self._clients.values()}

    def _send(self, ws, payload: dict) -> None:
        lock = self._send_locks.get(ws)
        if lock is None:
            lock = threading.Lock()
            self._send_locks[ws] = lock
        with lock:
            try:
                ws.send(json.dumps(payload))
            except Exception:
                self.remove_client(ws)

    def publish(self, key: str, payload: dict, exclude=None) -> int:
        with self._lock:
            targets = list(self._subs.get(key, ()))
        count = 0
        for ws in targets:
            if ws is exclude:
                continue
            self._send(ws, payload)
            count += 1
        return count

    def publish_to_user(self, user_id: int, payload: dict) -> int:
        return self.publish(f"user:{user_id}", payload)
