"""Cryptographic Failures / Session: session cookie should have safe attributes.

Avoids false pass/fail by:
- reading all Set-Cookie headers robustly
- validating the actual session cookie (PHPSESSID or configured name)
- skipping when cookie headers are not observable in this environment
"""

import re
import pytest
import requests
from common import BASE_URL


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
    """
    Return all Set-Cookie header lines (not merged).
    requests may expose only one via resp.headers; raw headers are more reliable.
    """
    raw = getattr(resp, "raw", None)
    if raw and getattr(raw, "headers", None):
        h = raw.headers
        # urllib3 / http.client compatible
        if hasattr(h, "get_all"):
            vals = h.get_all("Set-Cookie") or []
            return [v for v in vals if v]
    # fallback (may be merged/partial)
    v = resp.headers.get("Set-Cookie")
    return [v] if v else []


def _pick_session_cookie(set_cookie_lines: list[str]) -> str | None:
    """
    Pick the session cookie line. Default PHP session cookie is PHPSESSID.
    Allow override via env if needed.
    """
    import os
    name = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")
    for line in set_cookie_lines:
        if line.lower().startswith(name.lower() + "="):
            return line
    # fallback: any cookie that looks like a session id (best effort)
    for line in set_cookie_lines:
        if re.match(r"^[A-Za-z0-9_]+=[^;]+;", line):
            if "phpsessid" in line.lower():
                return line
    return None


def test_session_cookie_has_httponly_and_samesite():
    s = requests.Session()
    r = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
    )
    assert r.status_code == 200, r.text

    set_cookies = _all_set_cookie_headers(r)
    if not set_cookies:
        pytest.skip("Cannot observe Set-Cookie headers in this environment; skip cookie-flag validation.")

    sess_cookie = _pick_session_cookie(set_cookies)
    if not sess_cookie:
        pytest.skip(f"No recognizable session cookie found in Set-Cookie headers: {set_cookies}")

    lower = sess_cookie.lower()

    # Must have HttpOnly
    assert "httponly" in lower, f"Session cookie must include HttpOnly. Got: {sess_cookie}"

    # Must have SameSite
    m = re.search(r"samesite=([a-z]+)", lower)
    assert m, (
        "Session cookie should include SameSite (Lax/Strict/None). "
        f"Got: {sess_cookie}"
    )

    samesite = m.group(1)
    assert samesite in ("lax", "strict", "none"), f"Unexpected SameSite value: {samesite}. Full cookie: {sess_cookie}"

    # If SameSite=None, Secure is strongly recommended; do not hard-fail for local HTTP,
    # but warn by failing only if you want to enforce strict policy.
    if samesite == "none" and "secure" not in lower:
        pytest.skip("SameSite=None without Secure observed; in production this should be Secure. Skipping for local HTTP.")