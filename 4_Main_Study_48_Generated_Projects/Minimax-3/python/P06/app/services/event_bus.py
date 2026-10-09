from __future__ import annotations

import secrets
import threading
from collections import defaultdict
from typing import Any, Callable

_listeners: dict[str, list[Callable[[dict], None]]] = defaultdict(list)
_lock = threading.Lock()
_thread_local_buffer: dict[str, list[dict]] = defaultdict(list)


def publish(event_type: str, payload: dict[str, Any]) -> None:
    with _lock:
        listeners = list(_listeners.get(event_type, []))
    for fn in listeners:
        try:
            fn(payload)
        except Exception:
            pass


def subscribe(event_type: str, fn: Callable[[dict], None]) -> None:
    with _lock:
        _listeners[event_type].append(fn)


def unsubscribe(event_type: str, fn: Callable[[dict], None]) -> None:
    with _lock:
        if fn in _listeners.get(event_type, []):
            _listeners[event_type].remove(fn)


def push_event(user_id: int, event_type: str, data: dict[str, Any]) -> None:
    _thread_local_buffer[user_id].append({"type": event_type, "data": data})


def drain_events(user_id: int) -> list[dict[str, Any]]:
    return _thread_local_buffer.pop(user_id, [])


def new_event_id() -> str:
    return secrets.token_hex(8)
