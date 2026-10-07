You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


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


### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route definition for `GET /api/courses/<course_id>/search/posts` (search endpoint)
* MISSING: Search controller/handler that builds the SQL query using `keyword`
* MISSING: Post repository/DAO function that queries posts by keyword (likely uses SQL `LIKE`)
* MISSING: DB access layer (sqlite cursor/connection wrapper) used by the search query (where parameter binding vs string concat happens)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route wiring for `GET /api/courses/<course_id>/search/posts` and the handler name it calls.

File: MISSING

* Needed: handler logic that reads `keyword` from query params and passes it into DB query (must show whether it uses parameter placeholders or string interpolation).

File: MISSING

* Needed: repository/DAO SQL for searching posts (must show SQL shape, e.g. `... WHERE title LIKE '%{keyword}%' OR body LIKE ...` vs `... WHERE title LIKE ?`).

File: MISSING

* Needed: DB execution helper showing whether it uses `execute(sql)` vs `execute(sql, params)` and how exceptions are handled.

---

### 4.3 RootCause

RootCause:

* The search implementation likely **concatenates/interpolates** the `keyword` into the SQL string (e.g., `... LIKE '%" + keyword + "%'`), so the payload `' OR 1=1 --` turns the WHERE clause into a tautology and returns unrelated posts (e.g., `t2`), failing the “no broadening” oracle.
* Even if `LIKE` is used, missing parameter binding (or incorrect escaping) allows the injected `'` to break out of the string literal and alter SQL semantics.
* Enforcement must occur in the **search repository/DAO** (use parameterized queries) and the **controller** should treat `keyword` as data (optionally validate length/type) and never build SQL by string concatenation.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: `GET /api/courses/<course_id>/search/posts`
  Change: Read `keyword` as a plain string; pass it to the repository as a parameter value only. Do **not** embed it in SQL. Optionally: if keyword contains NUL/too long/empty, return 400 or treat as “no results” (but keep baseline search working).

* Target File: **Posts search repository/DAO** (MISSING)
  Target: SQL query that filters posts by `keyword`
  Change: Convert to parameterized SQL:

    * Use placeholders (`?`) and pass `%{keyword}%` as bound params for `LIKE`.
    * Ensure the query is scoped to the course (`course_id = ?`) so it cannot leak across courses either.
    * Example behavior requirement (not code): `WHERE course_id=? AND (title LIKE ? OR body LIKE ?)` with params `[course_id, like, like]`.

* Target File: **DB execution layer** (MISSING)
  Target: query execution helper used by search
  Change: Ensure all executions use bound parameters (`execute(sql, params)`), and on SQL errors return a controlled `400`/empty result without leaking DB internals (so future leak checks don’t fail).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller → stop building SQL strings from `keyword`; pass keyword to DAO as data
* MISSING: posts search DAO/repository → parameterize `LIKE` query; include `course_id` scoping; no string interpolation
* MISSING: DB helper/executor → enforce parameter binding usage and controlled error handling for malformed queries


### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route definition for `GET /api/courses/<course_id>/search/posts` (search endpoint)
* MISSING: Search controller/handler that builds the SQL query using `keyword`
* MISSING: Post repository/DAO function that queries posts by keyword (likely uses SQL `LIKE`)
* MISSING: DB access layer (sqlite cursor/connection wrapper) used by the search query (where parameter binding vs string concat happens)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route wiring for `GET /api/courses/<course_id>/search/posts` and the handler name it calls.

File: MISSING

* Needed: handler logic that reads `keyword` from query params and passes it into DB query (must show whether it uses parameter placeholders or string interpolation).

File: MISSING

* Needed: repository/DAO SQL for searching posts (must show SQL shape, e.g. `... WHERE title LIKE '%{keyword}%' OR body LIKE ...` vs `... WHERE title LIKE ?`).

File: MISSING

* Needed: DB execution helper showing whether it uses `execute(sql)` vs `execute(sql, params)` and how exceptions are handled.

---

### 4.3 RootCause

RootCause:

* The search implementation likely **concatenates/interpolates** the `keyword` into the SQL string (e.g., `... LIKE '%" + keyword + "%'`), so the payload `' OR 1=1 --` turns the WHERE clause into a tautology and returns unrelated posts (e.g., `t2`), failing the “no broadening” oracle.
* Even if `LIKE` is used, missing parameter binding (or incorrect escaping) allows the injected `'` to break out of the string literal and alter SQL semantics.
* Enforcement must occur in the **search repository/DAO** (use parameterized queries) and the **controller** should treat `keyword` as data (optionally validate length/type) and never build SQL by string concatenation.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: `GET /api/courses/<course_id>/search/posts`
  Change: Read `keyword` as a plain string; pass it to the repository as a parameter value only. Do **not** embed it in SQL. Optionally: if keyword contains NUL/too long/empty, return 400 or treat as “no results” (but keep baseline search working).

* Target File: **Posts search repository/DAO** (MISSING)
  Target: SQL query that filters posts by `keyword`
  Change: Convert to parameterized SQL:

    * Use placeholders (`?`) and pass `%{keyword}%` as bound params for `LIKE`.
    * Ensure the query is scoped to the course (`course_id = ?`) so it cannot leak across courses either.
    * Example behavior requirement (not code): `WHERE course_id=? AND (title LIKE ? OR body LIKE ?)` with params `[course_id, like, like]`.

* Target File: **DB execution layer** (MISSING)
  Target: query execution helper used by search
  Change: Ensure all executions use bound parameters (`execute(sql, params)`), and on SQL errors return a controlled `400`/empty result without leaking DB internals (so future leak checks don’t fail).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller → stop building SQL strings from `keyword`; pass keyword to DAO as data
* MISSING: posts search DAO/repository → parameterize `LIKE` query; include `course_id` scoping; no string interpolation
* MISSING: DB helper/executor → enforce parameter binding usage and controlled error handling for malformed queries


### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route definition for `GET /api/courses/<course_id>/search/posts` (search endpoint)
* MISSING: Search controller/handler that builds the SQL query using `keyword`
* MISSING: Post repository/DAO function that queries posts by keyword (likely uses SQL `LIKE`)
* MISSING: DB access layer (sqlite cursor/connection wrapper) used by the search query (where parameter binding vs string concat happens)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route wiring for `GET /api/courses/<course_id>/search/posts` and the handler name it calls.

File: MISSING

* Needed: handler logic that reads `keyword` from query params and passes it into DB query (must show whether it uses parameter placeholders or string interpolation).

File: MISSING

* Needed: repository/DAO SQL for searching posts (must show SQL shape, e.g. `... WHERE title LIKE '%{keyword}%' OR body LIKE ...` vs `... WHERE title LIKE ?`).

File: MISSING

* Needed: DB execution helper showing whether it uses `execute(sql)` vs `execute(sql, params)` and how exceptions are handled.

---

### 4.3 RootCause

RootCause:

* The search implementation likely **concatenates/interpolates** the `keyword` into the SQL string (e.g., `... LIKE '%" + keyword + "%'`), so the payload `' OR 1=1 --` turns the WHERE clause into a tautology and returns unrelated posts (e.g., `t2`), failing the “no broadening” oracle.
* Even if `LIKE` is used, missing parameter binding (or incorrect escaping) allows the injected `'` to break out of the string literal and alter SQL semantics.
* Enforcement must occur in the **search repository/DAO** (use parameterized queries) and the **controller** should treat `keyword` as data (optionally validate length/type) and never build SQL by string concatenation.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: `GET /api/courses/<course_id>/search/posts`
  Change: Read `keyword` as a plain string; pass it to the repository as a parameter value only. Do **not** embed it in SQL. Optionally: if keyword contains NUL/too long/empty, return 400 or treat as “no results” (but keep baseline search working).

* Target File: **Posts search repository/DAO** (MISSING)
  Target: SQL query that filters posts by `keyword`
  Change: Convert to parameterized SQL:

    * Use placeholders (`?`) and pass `%{keyword}%` as bound params for `LIKE`.
    * Ensure the query is scoped to the course (`course_id = ?`) so it cannot leak across courses either.
    * Example behavior requirement (not code): `WHERE course_id=? AND (title LIKE ? OR body LIKE ?)` with params `[course_id, like, like]`.

* Target File: **DB execution layer** (MISSING)
  Target: query execution helper used by search
  Change: Ensure all executions use bound parameters (`execute(sql, params)`), and on SQL errors return a controlled `400`/empty result without leaking DB internals (so future leak checks don’t fail).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller → stop building SQL strings from `keyword`; pass keyword to DAO as data
* MISSING: posts search DAO/repository → parameterize `LIKE` query; include `course_id` scoping; no string interpolation
* MISSING: DB helper/executor → enforce parameter binding usage and controlled error handling for malformed queries


Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"