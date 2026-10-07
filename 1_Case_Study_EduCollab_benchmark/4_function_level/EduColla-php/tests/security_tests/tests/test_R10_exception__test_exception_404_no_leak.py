"""Mishandling Exceptional Conditions: error responses should not leak internal details."""

import re
import pytest
import requests
from common import BASE_URL


LEAK_PATTERNS = [
    r"fatal error",                 # PHP fatal
    r"uncaught .*exception",        # PHP uncaught
    r"pdoexception",                # DB exception
    r"stack trace",                 # generic stack trace
    r"traceback \(most recent call last\)",  # Python traceback
    r"\bat\s+\w.*\(.+:\d+\)",       # Java-ish stack lines
    r"in\s+/var/",                  # unix paths
    r"in\s+/home/",                 # unix paths
    r"in\s+c:\\",                   # windows paths
    r"undefined (index|variable)",  # PHP notices
    r"\bnotice:\b",                 # PHP notice prefix
    r"\bwarning:\b",                # PHP warning prefix (more specific than 'warning' alone)
    r"on line \d+",                 # PHP line leak
]


def _assert_no_leak(resp: requests.Response):
    body = resp.text or ""
    lowered = body.lower()
    for pat in LEAK_PATTERNS:
        if re.search(pat, lowered):
            pytest.fail(
                "Internal detail leaked in error response.\n"
                f"status={resp.status_code} ct={resp.headers.get('Content-Type')}\n"
                f"matched={pat}\n"
                f"body_head={(body[:600])!r}"
            )


def test_error_responses_do_not_leak_internal_details():
    s = requests.Session()

    # 1) Unknown API route (commonly 404)
    r1 = s.get(f"{BASE_URL}/api/__definitely_not_a_real_route", timeout=10, allow_redirects=False)
    assert r1.status_code in (404, 400, 405), f"Unexpected status={r1.status_code} body_head={(r1.text or '')[:200]!r}"
    _assert_no_leak(r1)

    # 2) Malformed JSON to a known JSON endpoint (if exists) to trigger error handling paths.
    # Some apps may return 400/415/422/500; we only assert "no internal leak".
    r2 = s.post(
        f"{BASE_URL}/api/auth/login",
        data="{not-json",
        headers={"Content-Type": "application/json"},
        timeout=10,
        allow_redirects=False,
    )
    assert r2.status_code in (400, 401, 403, 415, 422, 500), (
        f"Unexpected status={r2.status_code} body_head={(r2.text or '')[:200]!r}"
    )
    _assert_no_leak(r2)