import requests


def test_exploit_session_fixation_login_reissues_flask_session_cookie(app_instance):
    fixed_cookie = "attacker-fixed-session-cookie"
    s = requests.Session()

    # Flask default cookie name is usually "session"
    s.cookies.set("session", fixed_cookie, path="/")

    r = s.post(
        f"{app_instance['base_url']}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # The application should not keep using the attacker-provided cookie value.
    new_cookie = s.cookies.get("session")
    assert new_cookie is not None
    assert new_cookie != fixed_cookie

    me = s.get(f"{app_instance['base_url']}/api/auth/me", timeout=5)
    assert me.status_code == 200, me.text
    assert me.json()["user"]["username"] == "admin"