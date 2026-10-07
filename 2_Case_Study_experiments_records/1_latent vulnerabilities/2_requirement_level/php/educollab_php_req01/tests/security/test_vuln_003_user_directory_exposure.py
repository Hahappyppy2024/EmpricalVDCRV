import requests

from helpers import api_url, json_of, register


def test_exploit_vuln_003_any_authenticated_user_can_list_all_users_api(app_instance):
    """
    Exploit expectation:
    Any authenticated non-admin user can read the full user directory through /api/users.
    """
    base_url = app_instance["base_url"]

    s = requests.Session()

    r = register(s, base_url, "ordinary_user", "pass123", "Ordinary User")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    users_resp = s.get(api_url(base_url, "/api/users"), timeout=10)
    assert users_resp.status_code == 200, users_resp.text

    payload = json_of(users_resp)
    assert payload["success"] is True
    assert isinstance(payload["users"], list)
    assert len(payload["users"]) >= 2

    usernames = {u["username"] for u in payload["users"]}
    assert "ordinary_user" in usernames
    assert "admin" in usernames


def test_exploit_vuln_003_any_authenticated_user_can_list_all_users_html(app_instance):
    """
    Exploit expectation:
    Any authenticated non-admin user can open /admin/users and see the user directory.
    """
    base_url = app_instance["base_url"]

    s = requests.Session()

    r = register(s, base_url, "ordinary_html_user", "pass123", "Ordinary HTML User")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    page = s.get(api_url(base_url, "/admin/users"), timeout=10)
    assert page.status_code == 200, page.text
    assert "ordinary_html_user" in page.text
    assert "admin" in page.text