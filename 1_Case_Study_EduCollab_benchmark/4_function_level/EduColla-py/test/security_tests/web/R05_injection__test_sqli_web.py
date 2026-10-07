# ============================================================
# Web (HTML) - SQL Injection
# ============================================================

def test_web_sqli_login_does_not_bypass(client, auth):
    payload = "' OR '1'='1"
    rv = auth.login(payload, "randompassword")

    assert b"Logout" not in rv.data
    assert rv.status_code in (200, 302)


def test_web_sqli_search_posts_union_does_not_crash_or_leak(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    payload = "test' UNION SELECT * FROM user--"
    rv = client.get(f"/courses/{course_id}/posts?keyword={payload}", follow_redirects=True)

    assert rv.status_code in (200, 400)
    assert b"password_hash" not in rv.data


def test_web_sqli_search_posts_tautology_does_not_return_sensitive_fields(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    payload = "' OR 1=1 --"
    rv = client.get(f"/courses/{course_id}/posts?keyword={payload}", follow_redirects=True)

    assert rv.status_code in (200, 400)
    # crude but practical: should not dump DB schema/fields
    assert b"password_hash" not in rv.data
    assert b"sqlite_master" not in rv.data
