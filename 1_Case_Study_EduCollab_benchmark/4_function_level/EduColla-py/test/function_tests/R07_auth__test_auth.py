def test_auth_register_login_logout_html(client, auth):
    rv = auth.register("u1", "p1", "User One")
    assert rv.status_code in (200, 302)

    rv = auth.login("u1", "p1")
    assert rv.status_code in (200, 302)

    rv = client.get("/", follow_redirects=True)
    assert rv.status_code == 200

    rv = auth.logout()
    assert rv.status_code in (200, 302)

    rv = client.get("/courses", follow_redirects=False)
    assert rv.status_code == 302
    assert "/login" in rv.headers.get("Location", "")


def test_auth_api_login_me_logout(client, auth):
    rv = client.post("/api/auth/register", json={"username": "apiu", "password": "p", "display_name": "API U"})
    assert rv.status_code in (201, 400)

    rv = auth.api_login("apiu", "p")
    assert rv.status_code in (200, 302)

    rv = client.get("/api/auth/me")
    assert rv.status_code == 200
    assert b"apiu" in rv.data

    rv = auth.api_logout()
    assert rv.status_code in (200, 302)

    rv = client.get("/api/auth/me")
    assert rv.status_code == 401
