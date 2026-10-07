def test_a09_failed_login_not_logged_in_audit_log(client):
    """A09: missing logging for critical security events (intentional).

    We intentionally do NOT log failed login attempts. This test asserts the absence
    of such records, making the A09 vulnerability reproducible.
    """

    # Trigger a failed login (critical event)
    rv = client.post("/api/auth/login", json={"username": "admin", "password": "wrong-password"})
    assert rv.status_code in (400, 401, 403)

    # Login correctly as admin to view audit log
    rv = client.post("/login", data={"username": "admin", "password": "admin123"}, follow_redirects=True)
    assert rv.status_code in (200, 302)

    rv = client.get("/api/admin/audit")
    assert rv.status_code == 200
    rows = (rv.get_json() or {}).get("audit", [])

    # A09: these actions should exist in a well-monitored system, but are intentionally missing.
    def is_failed_login_action(a: str) -> bool:
        a = (a or "").lower()
        return ("login_failed" in a) or ("failed_login" in a) or ("auth_failed" in a)

    assert all(not is_failed_login_action(r.get("action")) for r in rows)
