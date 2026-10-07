# ============================================================
# Web (HTML) - Session Management
# ============================================================

def test_web_logout_blocks_courses(client, auth):
    auth.register("secure_user", "pass", "Secure User")
    auth.login("secure_user", "pass")

    rv = client.get("/courses", follow_redirects=True)
    assert rv.status_code == 200

    auth.logout()

    rv = client.get("/courses", follow_redirects=False)
    assert rv.status_code == 302
    assert "/login" in rv.headers.get("Location", "")


def test_web_unauthenticated_redirects_to_login(client):
    rv = client.get("/courses", follow_redirects=False)
    assert rv.status_code in (302, 401, 403)
    if rv.status_code == 302:
        assert "/login" in rv.headers.get("Location", "")


def test_web_login_does_not_set_session_on_failed_login(client, auth):
    rv = auth.login("nonexistent_user", "wrongpass")
    assert rv.status_code in (200, 302)
    # crude check: should not show a logout link
    assert b"Logout" not in rv.data
