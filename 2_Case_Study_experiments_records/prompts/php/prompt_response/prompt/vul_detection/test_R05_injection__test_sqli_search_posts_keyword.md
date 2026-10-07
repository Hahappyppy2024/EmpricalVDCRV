
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)
SQL injection–safe search: the post-search endpoint must treat the keyword parameter as plain
data so an injection payload (e.g., ' OR 1=1 --) cannot broaden results or return unrelated posts.


### 3) Failed Security Test
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



You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)

Robust error handling: the API must reject malformed JSON with a 4xx response
(e.g., 400/415/422) and must not leak stack traces, file paths, DB errors, or secrets in the error body.


### 3) Failed Security Test
import re

# ============================================================
# API (JSON) - Mishandling Exceptional Conditions
# ============================================================

def _text(rv):
try:
return rv.get_data(as_text=True)
except Exception:
return (rv.data or b"").decode("utf-8", errors="ignore")


def _no_leak(text):
forbidden = [
r"Traceback", r"Werkzeug", r"Debugger",
r"File \"[^\"]+\.py\"", r"/[A-Za-z0-9_\-./]+\.py", r"\\[A-Za-z0-9_\-\\.]+\.py",
r"sqlite3\.", r"OperationalError", r"SECRET_KEY",
]
for pat in forbidden:
assert re.search(pat, text, re.I) is None, f"Leakage matched /{pat}/"


def test_api_malformed_json_returns_4xx_and_no_leak(client, auth, sample_course):
# Ensure authenticated (some APIs require auth)
auth.register("exc_api_user", "pass", "Exc API User")
auth.login("exc_api_user", "pass")

    # Choose a common JSON endpoint (course creation)
    rv = client.post(
        "/api/courses",
        data='{"title":',
        headers={"Content-Type": "application/json"},
        follow_redirects=False,
    )
    if rv.status_code == 404:
        # API not implemented in this variant
        return

    assert rv.status_code in (400, 415, 422), f"Expected 4xx for malformed JSON, got {rv.status_code}"
    _no_leak(_text(rv))

### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>