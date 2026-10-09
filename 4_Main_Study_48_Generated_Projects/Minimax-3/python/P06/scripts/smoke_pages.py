"""Verify frontend pages and websocket layer load as expected."""
from __future__ import annotations

import json
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


def main() -> int:
    app = create_app()
    from werkzeug.serving import make_server

    server = make_server(Config.HOST, Config.PORT, app, threaded=True)
    thread = Thread(target=server.serve_forever, daemon=True)
    thread.start()
    print(f"Server started on {Config.HOST}:{Config.PORT}")
    time.sleep(1.0)

    base = f"http://127.0.0.1:{Config.PORT}"

    log = []
    failed = 0

    cookies = CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))

    def fetch(path: str, expect_status: int = 200):
        url = f"{base}{path}"
        req = urllib.request.Request(url)
        try:
            with opener.open(req, timeout=5) as r:
                body = r.read().decode("utf-8", "replace")
                ok = r.status == expect_status
                log.append(f"{'OK' if ok else '!!'} {path:50} {r.status}")
                if not ok:
                    failed += 1
                return body
        except urllib.error.HTTPError as e:
            body = e.read().decode("utf-8", "replace")
            log.append(f"{'!!'} {path:50} {e.code}")
            failed += 1
            return body

    # Public pages
    body = fetch("/signin")
    assert "Sign in" in body
    body = fetch("/register")
    assert "Create account" in body
    body = fetch("/reset")
    assert "Request password reset" in body

    # Sign in via API
    signin_payload = json.dumps({"identifier": "admin", "password": "AdminPass123!"}).encode()
    req = urllib.request.Request(
        base + "/api/chat/accounts/signin",
        data=signin_payload,
        method="POST",
        headers={"Content-Type": "application/json"},
    )
    with opener.open(req, timeout=5) as r:
        result = json.loads(r.read().decode())
        assert r.status == 200, result

    # Dashboard page
    body = fetch("/app")
    assert "Workspaces" in body
    body = fetch("/app/dm")
    assert "DM threads" in body
    body = fetch("/app/admin")
    assert "Administration" in body

    # WebSocket route should be registered
    rules = [r.rule for r in app.url_map.iter_rules()]
    assert "/ws/chat" in rules, rules

    # Static assets
    body = fetch("/static/js/app.js")
    assert "export const api" in body
    body = fetch("/static/css/app.css")
    assert "--bg" in body

    # Final summary
    for line in log:
        print(line)
    print(f"\nFrontend probe complete with {failed} failure(s).")
    server.shutdown()
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
