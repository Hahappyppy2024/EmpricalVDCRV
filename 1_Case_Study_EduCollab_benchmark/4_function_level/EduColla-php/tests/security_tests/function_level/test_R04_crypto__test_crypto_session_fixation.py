"""Cryptographic Failures / Session: login should regenerate the session identifier.

Avoids false pass/fail by:
- targeting the real session cookie (default: PHPSESSID; override via env SESSION_COOKIE_NAME)
- not relying on /login always setting a cookie
- verifying login actually succeeded via /api/auth/me
- comparing both cookie jar value and Set-Cookie value after login
"""

import os
import re
import pytest
import requests

from common import BASE_URL


SESSION_COOKIE_NAME = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
    raw = getattr(resp, "raw", None)
    if raw and getattr(raw, "headers", None) and hasattr(raw.headers, "get_all"):
        return raw.headers.get_all("Set-Cookie") or []
    v = resp.headers.get("Set-Cookie")
    return [v] if v else []


def _extract_cookie_value_from_set_cookie(set_cookie_line: str, name: str) -> str | None:
    # e.g., "PHPSESSID=abc; path=/; HttpOnly; SameSite=Lax"
    m = re.match(rf"^{re.escape(name)}=([^;]*)", set_cookie_line, flags=re.IGNORECASE)
    return m.group(1) if m else None


def _get_session_id_from_jar(s: requests.Session) -> str | None:
    v = s.cookies.get(SESSION_COOKIE_NAME)
    return v if v else None


def _ensure_prelogin_session(s: requests.Session) -> str | None:
    """
    Try a couple of endpoints to force session creation.
    Return session id if obtained, else None.
    """
    # 1) HTML login page often initializes session
    s.get(f"{BASE_URL}/login", timeout=10, allow_redirects=False)
    sid = _get_session_id_from_jar(s)
    if sid:
        return sid

    # 2) an API GET may also set a session cookie
    s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
    sid = _get_session_id_from_jar(s)
    return sid


def _assert_logged_in(s: requests.Session):
    r = s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
    # Some apps use 200 with JSON; others might use 401 when not logged in.
    assert r.status_code == 200, f"Login did not establish session. /api/auth/me -> {r.status_code} body={r.text[:200]}"
    try:
        data = r.json()
    except Exception:
        pytest.fail(f"/api/auth/me did not return JSON. body_head={r.text[:200]}")
    assert "user" in data, f"/api/auth/me JSON missing user. keys={list(data.keys())}"


def test_login_regenerates_session_id():
    s = requests.Session()

    sid_before = _ensure_prelogin_session(s)
    if not sid_before:
        pytest.skip(
            f"No {SESSION_COOKIE_NAME} cookie was set before login; cannot test fixation in a black-box way. "
            "Ensure the app initializes a session on first visit or expose a pre-login endpoint that sets it."
        )

    # Perform login
    r1 = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
        allow_redirects=False,
    )
    assert r1.status_code == 200, r1.text[:200]

    # Confirm login actually succeeded (avoid false pass where cookie changes but auth fails)
    _assert_logged_in(s)

    # Read session id after login (from cookie jar)
    sid_after = _get_session_id_from_jar(s)
    assert sid_after, f"Post-login {SESSION_COOKIE_NAME} cookie missing."

    # Also try to read the newly-set session id from Set-Cookie (stronger signal)
    set_cookies = _all_set_cookie_headers(r1)
    sid_after_setcookie = None
    for line in set_cookies:
        v = _extract_cookie_value_from_set_cookie(line, SESSION_COOKIE_NAME)
        if v is not None:
            sid_after_setcookie = v
            break

    # Primary assertion: session id must change after login
    assert sid_after != sid_before, (
        f"Session id did not change after login (cookie {SESSION_COOKIE_NAME}). "
        "Mitigation: call session_regenerate_id(true) after successful login."
    )

    # Secondary consistency check (if we observed Set-Cookie)
    if sid_after_setcookie is not None:
        assert sid_after_setcookie == sid_after, (
            f"Mismatch between Set-Cookie {SESSION_COOKIE_NAME} and cookie jar. "
            f"setcookie={sid_after_setcookie!r} jar={sid_after!r}"
        )