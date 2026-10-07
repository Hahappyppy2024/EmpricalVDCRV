import requests


def test_exploit_session_fixation_login_does_not_rotate_php_session_id(app_instance):
    fixed_sid = "fixedattackersession123"
    s = requests.Session()
    s.cookies.set("PHPSESSID", fixed_sid, path="/")

    r = s.post(
        f"{app_instance['base_url']}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    me = s.get(f"{app_instance['base_url']}/api/auth/me", timeout=5)
    assert me.status_code == 200, me.text
    assert me.json()["user"]["username"] == "admin"
    assert s.cookies.get("PHPSESSID") == fixed_sid