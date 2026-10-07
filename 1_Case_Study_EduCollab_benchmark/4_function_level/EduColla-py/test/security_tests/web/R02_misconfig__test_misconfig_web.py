import re

# ============================================================
# Web (HTML) - Security Misconfiguration
# ============================================================

def _resp_text(rv):
    try:
        return rv.get_data(as_text=True)
    except Exception:
        return (rv.data or b"").decode("utf-8", errors="ignore")


def test_web_session_cookie_has_httponly_and_samesite(client, auth):
    # Register and login without following redirects to capture Set-Cookie
    auth.register("misconf_user", "pass", "Misconf User")
    rv = client.post("/login", data={"username": "misconf_user", "password": "pass"}, follow_redirects=False)

    # Some apps may redirect after login; cookie is usually set on this response
    cookies = rv.headers.getlist("Set-Cookie")
    assert cookies, "Expected Set-Cookie on login response"
    cookie_str = " ".join(cookies)

    assert re.search(r"HttpOnly", cookie_str, re.I), f"Missing HttpOnly in Set-Cookie: {cookie_str}"
    # SameSite may be absent in some implementations; this test enforces it for misconfig coverage
    assert re.search(r"SameSite=", cookie_str, re.I), f"Missing SameSite in Set-Cookie: {cookie_str}"


def test_web_404_does_not_expose_debugger_or_internal_paths(client):
    rv = client.get("/__does_not_exist__", follow_redirects=False)
    assert rv.status_code in (404, 302)

    # If redirected to login, that's fine; still check body for leakage
    body = _resp_text(rv)

    forbidden = [
        r"Traceback", r"Werkzeug", r"Debugger", r"Internal Server Error",
        r"File \"[^\"]+\.py\"", r"/[A-Za-z0-9_\-./]+\.py", r"\\[A-Za-z0-9_\-\\.]+\.py",
        r"sqlite3\.", r"OperationalError", r"SECRET_KEY", r"password_hash",
    ]
    for pat in forbidden:
        assert re.search(pat, body, re.I) is None, f"Leakage matched /{pat}/ in 404 body"
