import urllib.parse
from .._helpers import api_login, api_get

# ============================================================
# API (JSON) - SQL Injection (stronger oracles)
# ============================================================

def test_api_sqli_login_does_not_bypass(client):
    payload = "' OR '1'='1"

    # attempt login with injection username
    rv = api_login(client, payload, "randompassword")

    # "pass" criteria: after this request, we still can't access a protected endpoint.
    # Pick one endpoint in your app that requires login.
    # Common choices: /api/courses, /api/me, /api/profile
    probe = api_get(client, "/api/courses", expected_status=(401, 403, 404, 405, 200))

    # If the endpoint exists and returns 200, that means we're logged in -> FAIL (bypass)
    if probe.status_code == 200:
        assert False, "Possible SQLi login bypass: injection login appears to grant authenticated access"


def _get_posts_json(client, course_id, keyword):
    q = urllib.parse.quote(keyword, safe="")
    rv = api_get(
        client,
        f"/api/courses/{course_id}/posts?keyword={q}",
        expected_status=(200, 400, 401, 403, 404),
    )
    if rv.status_code == 200 and rv.mimetype == "application/json":
        return rv.get_json()
    return None


def test_api_sqli_search_posts_tautology_does_not_expand_results(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    # Baseline: a keyword that should return empty or very small results
    baseline = _get_posts_json(client, course_id, "zzzz_no_such_keyword_zzzz")
    injected = _get_posts_json(client, course_id, "' OR 1=1 --")

    # If API not implemented / not returning JSON, we can't judge -> skip
    if baseline is None or injected is None:
        import pytest
        pytest.skip("Posts search API not implemented or not returning JSON; cannot assert SQLi effect")

    # Stronger oracle: injection should NOT increase results compared to baseline
    # (If your app legitimately returns all posts for empty/unknown keyword, adjust this oracle.)
    assert isinstance(baseline, list) and isinstance(injected, list)
    assert len(injected) <= len(baseline), "Possible SQLi: tautology payload expanded result set"

    # Still keep leakage checks
    import urllib.parse

    payload = "' OR 1=1 --"
    q = urllib.parse.quote(payload, safe="")

    rv2 = client.get(f"/api/courses/{course_id}/posts?keyword={q}")
    body = rv2.data or b""
    assert b"password_hash" not in body
    assert b"sqlite_master" not in body


def test_api_sqli_search_posts_union_does_not_leak_user_table(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    baseline = _get_posts_json(client, course_id, "zzzz_no_such_keyword_zzzz")
    injected = _get_posts_json(client, course_id, "test' UNION SELECT * FROM user--")

    if baseline is None or injected is None:
        import pytest
        pytest.skip("Posts search API not implemented or not returning JSON; cannot assert SQLi effect")

    assert isinstance(baseline, list) and isinstance(injected, list)

    # Union payload should not cause obvious "user table" artifacts
    injected_bytes = str(injected).encode("utf-8", errors="ignore")
    assert b"password_hash" not in injected_bytes
    assert b"admin" not in injected_bytes, "Possible SQLi leak: 'admin' appears in search response"

    # Also: results should not jump wildly compared to baseline
    assert len(injected) <= max(len(baseline), 3), "Possible SQLi: union payload caused abnormal results"