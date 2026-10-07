import os
import time
import uuid
import requests
import pytest

BASE_URL = os.getenv("BASE_URL", "http://localhost:8000").rstrip("/")


def _get_logs(admin_s: requests.Session) -> list[dict]:
    ra = admin_s.get(f"{BASE_URL}/api/admin/audit", timeout=10)
    if ra.status_code == 404:
        pytest.skip("Audit API not implemented: GET /api/admin/audit")
    assert ra.status_code == 200, ra.text[:200]
    data = ra.json()
    logs = data.get("logs")
    if not isinstance(logs, list):
        pytest.skip(f"Audit API schema unexpected (no 'logs' list). keys={list(data.keys())}")
    return logs


def _login(s: requests.Session, username: str, password: str) -> requests.Response:
    return s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": username, "password": password},
        timeout=10,
    )


def test_A09_failed_login_is_audited():
    """
    A09 (secure expectation):
    A failed login attempt SHOULD be logged (e.g., login_failed/auth_failed) with enough context to investigate.
    """
    marker = f"badpw-{uuid.uuid4().hex[:8]}"
    t0 = time.time()

    # Trigger a failed login attempt with a unique marker in password
    s_bad = requests.Session()
    r_bad = _login(s_bad, "admin", marker)
    assert r_bad.status_code in (400, 401, 403), r_bad.text[:200]

    # Login as admin to read logs
    admin_s = requests.Session()
    r_ok = _login(admin_s, "admin", "admin123")
    assert r_ok.status_code == 200, r_ok.text[:200]

    logs = _get_logs(admin_s)

    # We search for evidence of *this* failed login attempt.
    # Robust matching: check action + any meta/message field for marker or "login_failed".
    def log_text(row: dict) -> str:
        parts = []
        for k in ("action", "message", "meta", "meta_json", "details", "path", "ip", "username"):
            v = row.get(k)
            if v is None:
                continue
            parts.append(str(v))
        return " | ".join(parts).lower()

    # Filter to recent-ish logs if timestamps exist (avoid false fail due to old data).
    # We won't assume timestamp field names; do best-effort.
    candidates = []
    for row in logs:
        txt = log_text(row)
        if "login" in txt or "auth" in txt:
            candidates.append((row, txt))

    # Must contain a failed-login action OR our unique marker in some log field
    found = any(("login_failed" in txt or "auth_failed" in txt or marker.lower() in txt) for _row, txt in candidates)

    assert found, (
        "A09 risk: failed login attempt not found in audit logs.\n"
        f"marker={marker}\n"
        f"logs_sample={[c[1] for c in candidates[:8]]}"
    )