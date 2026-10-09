"""Run the Flask app in a thread then run smoke checks, all in one process."""
from __future__ import annotations

import json
import secrets
import sys
import time
import urllib.error
import urllib.request
from http.cookiejar import CookieJar
from threading import Thread

ROOT = "."
sys.path.insert(0, ROOT)

from app import create_app  # noqa: E402
from app.config import Config  # noqa: E402
from app.db import Base, _engine
from scripts import seed  # noqa: E402


def main() -> int:
    Base.metadata.drop_all(bind=_engine)
    Base.metadata.create_all(bind=_engine)
    seed.main()

    app = create_app()
    from werkzeug.serving import make_server

    server = make_server(Config.HOST, Config.PORT, app, threaded=True)
    thread = Thread(target=server.serve_forever, daemon=True)
    thread.start()
    print(f"Server started on {Config.HOST}:{Config.PORT}")
    time.sleep(1.0)

    base = f"http://127.0.0.1:{Config.PORT}"
    cookies = CookieJar()
    log: list[str] = []

    def call(method, path, payload=None):
        url = f"{base}{path}"
        data = None
        headers = {"Accept": "application/json"}
        if payload is not None:
            data = json.dumps(payload).encode()
            headers["Content-Type"] = "application/json"
        opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(cookies)
        )
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with opener.open(req, timeout=5) as resp:
                body = resp.read().decode()
                log.append(f"{method:6} {path:60} {resp.status}")
                return resp.status, _safe_json(body)
        except urllib.error.HTTPError as exc:
            body = exc.read().decode()
            log.append(f"{method:6} {path:60} {exc.code} ERROR")
            return exc.code, _safe_json(body)

    def _safe_json(text):
        try:
            return json.loads(text)
        except ValueError:
            return text

    failed = []
    try:
        status, body = call("GET", "/healthz")
        assert status == 200 and body.get("status") == "ok", body

        status, body = call("GET", "/api/chat/accounts")
        assert status == 200, body

        status, body = call(
            "POST",
            "/api/chat/accounts/signin",
            {"identifier": "alice", "password": "AlicePass123!"},
        )
        assert status == 200 and body.get("username") == "alice", body

        status, body = call("GET", "/api/chat/accounts/me")
        assert status == 200 and body.get("username") == "alice", body

        status, body = call("GET", "/api/chat/workspaces_and_channels")
        assert status == 200 and body["items"], body
        workspace_id = body["items"][0]["id"]

        status, body = call(
            "GET",
            f"/api/chat/workspaces_and_channels/{workspace_id}/channels",
        )
        assert status == 200 and body["items"], body
        channel_id = body["items"][0]["id"]

        client_id = "smoke-" + secrets.token_hex(4)

        status, body = call(
            "POST",
            f"/api/chat/real_time_messaging/{channel_id}/messages",
            {"body": "Smoke test message", "client_id": client_id},
        )
        assert status == 200 and body.get("body") == "Smoke test message", body
        message_id = body["id"]

        status, body = call(
            "POST",
            f"/api/chat/real_time_messaging/{channel_id}/messages",
            {"body": "Smoke test message", "client_id": client_id},
        )
        assert status == 200 and body.get("deduplicated") is True, body

        status, body = call(
            "PATCH",
            f"/api/chat/real_time_messaging/{message_id}",
            {"body": "Smoke test edited"},
        )
        assert status == 200 and body.get("edited") is True, body
        assert body.get("body") == "Smoke test edited", body

        status, body = call(
            "POST",
            "/api/chat/link_preview",
            {"url": "https://example.com/"},
        )
        assert status == 200 and body.get("title") == "Example Domain", body

        status, body = call(
            "POST",
            "/api/chat/connection_and_message_handling/connections",
            {},
        )
        assert status == 200 and body.get("connection_id"), body
        connection_id = body["connection_id"]

        status, body = call(
            "POST",
            f"/api/chat/connection_and_message_handling/connections/{connection_id}/events",
            {"last_event_id": "evt-1", "state": "delivered"},
        )
        assert status == 200 and body.get("last_event_id") == "evt-1", body

        status, body = call(
            "DELETE",
            f"/api/chat/connection_and_message_handling/connections/{connection_id}",
        )
        assert status == 200, body

        status, body = call("POST", "/api/chat/errors", {"code": "smoke", "message": "ok"})
        assert status == 200, body

        status, body = call(
            "POST",
            "/api/chat/frontend_api_integration",
            {"context": "smoke", "status": "loading"},
        )
        assert status == 200, body

        status, body = call("GET", "/api/chat/message_history_and_search?query=smoke")
        assert status == 200, body

        status, body = call("GET", "/api/chat/direct_messages")
        assert status == 200, body

        status, body = call("GET", "/api/chat/attachments")
        assert status == 200, body

        status, body = call("GET", f"/api/chat/channel_management/{channel_id}")
        assert status == 200, body

        status, body = call(
            "POST",
            f"/api/chat/workspaces_and_channels/{workspace_id}/members",
            {"email": f"smoke-{secrets.token_hex(4)}@example.com", "role": "member"},
        )
        assert status == 200 and body.get("token"), body
        invitation_token = body["token"]

        status, body = call(
            "POST",
            f"/api/chat/membership_lifecycle/invitations/{invitation_token}/decline",
            {},
        )
        assert status == 200, body

        status, body = call("POST", "/api/chat/accounts/signout")
        assert status == 200, body

    except AssertionError as exc:
        failed.append(("assert", str(exc)))

    server.shutdown()
    for line in log:
        print(line)
    if failed:
        print("\nFAILED:", failed)
        return 1
    print("\nAll smoke checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
