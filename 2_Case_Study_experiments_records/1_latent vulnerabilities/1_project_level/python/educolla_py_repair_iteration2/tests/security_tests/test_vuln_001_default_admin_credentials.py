import requests
import os

def test_exploit_default_admin_credentials_work(app_instance):
    base_url = app_instance["base_url"]
    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text
    data = r.json()
    assert data["user"]["username"] == "admin"