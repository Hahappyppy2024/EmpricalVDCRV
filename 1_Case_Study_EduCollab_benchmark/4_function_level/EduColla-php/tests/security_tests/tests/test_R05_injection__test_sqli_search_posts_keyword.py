id="1x6q6p"
"""SQLi check (robust): keyword search must not be vulnerable to classic OR-true injection.

Avoids false pass/fail by:
- verifying the search endpoint works with a normal keyword first (baseline)
- creating 2 distinguishable posts and checking injection does not broaden results
- not relying on the result being strictly empty (implementations vary), but on "no broadening" and "no unrelated matches"
"""

import pytest
from common import (
    api_login_admin,
    api_register_and_login_student,
    api_create_course,
    api_add_member,
    api_create_post,
    BASE_URL,
)


def _get_posts(resp_json: dict) -> list:
    # allow a couple of common shapes
    if isinstance(resp_json, dict):
        if "posts" in resp_json and isinstance(resp_json["posts"], list):
            return resp_json["posts"]
        if "data" in resp_json and isinstance(resp_json["data"], dict) and isinstance(resp_json["data"].get("posts"), list):
            return resp_json["data"]["posts"]
    return []


def _post_titles(posts: list) -> set[str]:
    out = set()
    for p in posts:
        if isinstance(p, dict) and "title" in p:
            out.add(str(p["title"]))
    return out


def test_search_posts_keyword_not_sqli():
    admin = api_login_admin()
    course = api_create_course(admin, title="sqli-course")

    stu_s, stu = api_register_and_login_student()
    course_id = int(course["course_id"])
    api_add_member(admin, course_id, int(stu["user_id"]), "student")

    # Two distinguishable posts
    t1 = "SAFE_UNIQUE_TITLE_12345"
    t2 = "ANOTHER_TITLE_99999"
    api_create_post(stu_s, course_id, title=t1, body="body-1")
    api_create_post(stu_s, course_id, title=t2, body="body-2")

    search_url = f"{BASE_URL}/api/courses/{course_id}/search/posts"

    # --- Baseline: search must work for a normal keyword
    r_ok = stu_s.get(search_url, params={"keyword": "SAFE_UNIQUE_TITLE_12345"}, timeout=10)
    if r_ok.status_code == 404:
        pytest.skip(f"Search endpoint not found: {search_url}")
    assert r_ok.status_code == 200, r_ok.text[:200]

    try:
        ok_json = r_ok.json()
    except Exception:
        pytest.fail(f"Search did not return JSON. ct={r_ok.headers.get('Content-Type')} head={r_ok.text[:200]}")
    ok_posts = _get_posts(ok_json)
    ok_titles = _post_titles(ok_posts)

    # must find at least the matching post; otherwise a "always empty" implementation would cause false pass later
    assert t1 in ok_titles, f"Baseline search failed to find known post. titles={sorted(ok_titles)[:10]}"

    # --- Injection attempt: should not broaden results or return unrelated posts
    inj = "' OR 1=1 --"
    r_inj = stu_s.get(search_url, params={"keyword": inj}, timeout=10)
    assert r_inj.status_code == 200, r_inj.text[:200]

    try:
        inj_json = r_inj.json()
    except Exception:
        pytest.fail(f"Injection search did not return JSON. ct={r_inj.headers.get('Content-Type')} head={r_inj.text[:200]}")

    inj_posts = _get_posts(inj_json)
    inj_titles = _post_titles(inj_posts)

    # Core checks:
    # 1) Injection must NOT return "everything" (broadening). A common broadening symptom: returns unrelated post(s).
    assert t2 not in inj_titles, (
        "Possible SQLi: injection query returned an unrelated post.\n"
        f"inj_titles={sorted(inj_titles)[:20]}"
    )

    # 2) Injection results should not be larger than a benign "nonsense" keyword query.
    # This avoids strict len==0 while still detecting broadening.
    nonsense = "zzzzzz_no_such_keyword_zzzzzz"
    r_none = stu_s.get(search_url, params={"keyword": nonsense}, timeout=10)
    assert r_none.status_code == 200, r_none.text[:200]
    try:
        none_json = r_none.json()
    except Exception:
        pytest.fail(f"Nonsense search did not return JSON. head={r_none.text[:200]}")
    none_posts = _get_posts(none_json)

    assert len(inj_posts) <= len(none_posts), (
        "Possible SQLi: injection broadened results compared to a nonsense keyword.\n"
        f"len(inj)={len(inj_posts)} len(nonsense)={len(none_posts)}\n"
        f"inj_titles={sorted(inj_titles)[:20]}"
    )