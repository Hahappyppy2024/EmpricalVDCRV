import urllib.parse
from .._helpers import api_login, api_get

# ============================================================
# API (JSON) - SQL Injection (stronger oracles)
# ============================================================


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
