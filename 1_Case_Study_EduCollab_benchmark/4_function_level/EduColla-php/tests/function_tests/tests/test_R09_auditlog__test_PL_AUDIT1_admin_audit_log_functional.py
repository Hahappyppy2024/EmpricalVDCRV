import requests
from conftest import BASE_URL

def test_PL_AUDIT1_admin_can_view_audit_log_page_and_api():
    s = requests.Session()

    # login as global admin
    r = s.post(f"{BASE_URL}/api/auth/login", json={"username": "admin", "password": "admin123"}, timeout=10)
    assert r.status_code == 200, r.text

    # HTML page exists
    rv = s.get(f"{BASE_URL}/admin/audit", timeout=10)
    assert rv.status_code == 200
    assert "Audit Log" in rv.text

    # API listing exists
    ra = s.get(f"{BASE_URL}/api/admin/audit", timeout=10)
    assert ra.status_code == 200
    data = ra.json()
    assert "logs" in data
    # viewing the page should create at least one non-critical audit_view entry
    actions = [row.get("action") for row in data.get("logs", [])]
    assert "audit_view" in actions
