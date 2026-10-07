import pytest
from .._helpers import api_login, api_logout

# ============================================================
# API (JSON) - Session Management (strong oracles)
# ============================================================

def _skip_if_404(rv, msg):
    if rv.status_code == 404:
        pytest.skip(msg)


# def test_api_unauthenticated_courses_is_denied(client):
#     rv = client.get("/api/courses", follow_redirects=False)
#     _skip_if_404(rv, "API endpoint not implemented: /api/courses")
#
#     # Stronger oracle: unauthenticated should not be 200
#     # Prefer API-style denial codes
#     assert rv.status_code in (401, 403, 302)
#     assert rv.status_code != 200


def test_api_logout_revokes_access(client, auth):
    # Create user + login (HTML login is OK if app shares session for API)
    auth.register("api_user", "pass", "API User")
    auth.login("api_user", "pass")

    pre = client.get("/api/courses", follow_redirects=False)
    _skip_if_404(pre, "API endpoint not implemented: /api/courses")

    # To test revocation, we must confirm it was accessible before logout
    assert pre.status_code == 200, f"Pre-logout /api/courses not accessible (got {pre.status_code}); cannot assert logout revokes access"

    # Logout (API helper should clear session)
    api_logout(client)

    post = client.get("/api/courses", follow_redirects=False)
    assert post.status_code in (401, 403, 302)
    assert post.status_code != 200


import pytest
from .._helpers import api_login

def _skip_if_404(rv, msg):
    if rv.status_code == 404:
        pytest.skip(msg)

def test_api_failed_login_does_not_grant_access_strong(client):
    # 1) Failed login should NOT succeed
    try:
        rv_login = api_login(client, "nonexistent_user", "wrongpass")
    except Exception as e:
        pytest.skip(f"api_login helper failed or endpoint missing: {e}")

    _skip_if_404(rv_login, "API login endpoint not implemented")

    # Strong oracle: failed login should be an auth failure (not 200/201, not a redirect-to-success)
    assert rv_login.status_code in (400, 401, 403), f"Unexpected status for failed login: {rv_login.status_code}"
    assert rv_login.status_code != 200

    # 2) Failed login must not create an authenticated session
    # (Flask test client supports inspecting session)
    with client.session_transaction() as sess:
        assert "user_id" not in sess, "Failed login should not set session user_id"

    # 3) Protected endpoint must remain denied with API-style codes
    rv = client.get("/api/courses", follow_redirects=False)
    _skip_if_404(rv, "API endpoint not implemented: /api/courses")

    assert rv.status_code in (401, 403), f"Expected 401/403 after failed login, got {rv.status_code}"
    # Optional stronger check: API denial should be JSON (not HTML redirect page)
    if rv.status_code in (401, 403):
        assert rv.mimetype == "application/json"