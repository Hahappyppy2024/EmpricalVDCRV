def test_a09_admin_can_view_audit_log_web_and_api(client, auth):
    # Seeded admin user
    rv = auth.login("admin", "admin123")
    assert rv.status_code in (200, 302)

    rv = client.get("/admin/audit", follow_redirects=True)
    assert rv.status_code == 200
    assert b"Audit Log" in rv.data

    rv = client.get("/api/admin/audit")
    assert rv.status_code == 200
    data = rv.get_json()
    assert "audit" in data
    assert isinstance(data["audit"], list)
    # viewing the page/api should create at least one audit_view record
    assert any((r.get("action") == "audit_view") for r in data["audit"])
