"""Mishandling Exceptional Conditions: malformed JSON should fail safely.

The server should return a controlled 4xx response (preferably 400) and must not
leak PHP warnings, stack traces, file paths, or SQL errors.
"""

from common import api_login_admin, BASE_URL


def test_malformed_json_returns_4xx_without_leakage():
    admin = api_login_admin()

    bad_json = '{"title": "X",'
    r = admin.post(
        f"{BASE_URL}/api/courses",
        data=bad_json,
        headers={"Content-Type": "application/json"},
        timeout=10,
    )

    assert r.status_code in (400, 415), r.text
    body = r.text or ""
    lowered = body.lower()

    forbidden_markers = [
        "fatal error",
        "warning",
        "notice",
        "stack trace",
        "traceback",
        "pdoexception",
        "in /var/",
        "in c:\\",
        "select ",
    ]
    assert not any(m in lowered for m in forbidden_markers), body
