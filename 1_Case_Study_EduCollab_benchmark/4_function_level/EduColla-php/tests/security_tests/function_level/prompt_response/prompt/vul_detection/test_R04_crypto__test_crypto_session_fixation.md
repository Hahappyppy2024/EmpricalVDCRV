





You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
Session fixation mitigation: the application must regenerate
(rotate) the session ID after a successful login so the pre-login session identifier cannot be reused.


### 3) Failed Security Test
import os
import re
import pytest
import requests

from common import BASE_URL


SESSION_COOKIE_NAME = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
    raw = getattr(resp, "raw", None)
    if raw and getattr(raw, "headers", None) and hasattr(raw.headers, "get_all"):
        return raw.headers.get_all("Set-Cookie") or []
    v = resp.headers.get("Set-Cookie")
    return [v] if v else []


def _extract_cookie_value_from_set_cookie(set_cookie_line: str, name: str) -> str | None:
    # e.g., "PHPSESSID=abc; path=/; HttpOnly; SameSite=Lax"
    m = re.match(rf"^{re.escape(name)}=([^;]*)", set_cookie_line, flags=re.IGNORECASE)
    return m.group(1) if m else None


def _get_session_id_from_jar(s: requests.Session) -> str | None:
    v = s.cookies.get(SESSION_COOKIE_NAME)
    return v if v else None


def _ensure_prelogin_session(s: requests.Session) -> str | None:
    """
    Try a couple of endpoints to force session creation.
    Return session id if obtained, else None.
    """
    # 1) HTML login page often initializes session
    s.get(f"{BASE_URL}/login", timeout=10, allow_redirects=False)
    sid = _get_session_id_from_jar(s)
    if sid:
        return sid

    # 2) an API GET may also set a session cookie
    s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
    sid = _get_session_id_from_jar(s)
    return sid


def _assert_logged_in(s: requests.Session):
    r = s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
    # Some apps use 200 with JSON; others might use 401 when not logged in.
    assert r.status_code == 200, f"Login did not establish session. /api/auth/me -> {r.status_code} body={r.text[:200]}"
    try:
        data = r.json()
    except Exception:
        pytest.fail(f"/api/auth/me did not return JSON. body_head={r.text[:200]}")
    assert "user" in data, f"/api/auth/me JSON missing user. keys={list(data.keys())}"


def test_login_regenerates_session_id():
    s = requests.Session()

    sid_before = _ensure_prelogin_session(s)
    if not sid_before:
        pytest.skip(
            f"No {SESSION_COOKIE_NAME} cookie was set before login; cannot test fixation in a black-box way. "
            "Ensure the app initializes a session on first visit or expose a pre-login endpoint that sets it."
        )

    # Perform login
    r1 = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
        allow_redirects=False,
    )
    assert r1.status_code == 200, r1.text[:200]

    # Confirm login actually succeeded (avoid false pass where cookie changes but auth fails)
    _assert_logged_in(s)

    # Read session id after login (from cookie jar)
    sid_after = _get_session_id_from_jar(s)
    assert sid_after, f"Post-login {SESSION_COOKIE_NAME} cookie missing."

    # Also try to read the newly-set session id from Set-Cookie (stronger signal)
    set_cookies = _all_set_cookie_headers(r1)
    sid_after_setcookie = None
    for line in set_cookies:
        v = _extract_cookie_value_from_set_cookie(line, SESSION_COOKIE_NAME)
        if v is not None:
            sid_after_setcookie = v
            break

    # Primary assertion: session id must change after login
    assert sid_after != sid_before, (
        f"Session id did not change after login (cookie {SESSION_COOKIE_NAME}). "
        "Mitigation: call session_regenerate_id(true) after successful login."
    )

    # Secondary consistency check (if we observed Set-Cookie)
    if sid_after_setcookie is not None:
        assert sid_after_setcookie == sid_after, (
            f"Mismatch between Set-Cookie {SESSION_COOKIE_NAME} and cookie jar. "
            f"setcookie={sid_after_setcookie!r} jar={sid_after!r}"
        )

### 4) Relevant Application Code (ONLY what is pasted exists)
{CODE_SNIPPETS: routes + middleware + controller (+ repo/view if needed)}

### 5) Output Requirements (STRICT)
Your response MUST contain exactly the following 7 sections, in this exact order, with the exact headings.
- Do NOT add extra sections.
- Do NOT omit any section.
- Do NOT output any code diff.

[1] VulnType
Choose exactly ONE label from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulnType: Rxx

[2] TestMapping
2–4 bullets mapping: (expected by test) -> (observed) -> (code cause).
Each bullet MUST reference a concrete code point shown (route/middleware/function name).
Format:
TestMapping:
- ...
- ...

[3] RootCause
2–4 bullets pointing to the exact missing/incorrect check and where it should be enforced (route/middleware/controller).
Format:
RootCause:
- ...
- ...

[4] RepairPlan
3–7 bullets. EACH bullet MUST include BOTH:
- Target: <file/snippet name + function/route>
- Change: <exact edit>
  Format:
  RepairPlan:
- Target: ... | Change: ...
- Target: ... | Change: ...
- Target: ... | Change: ...

[5] ReliabilityClaim
Exactly ONE sentence explaining why this diagnosis is supported by the given test + code (no speculation).
Format:
ReliabilityClaim: ...

[6] RegressionRisks
1–3 bullets listing what might break and what should be rechecked later.
Format:
RegressionRisks:
- ...
- ...

[7] SelfCheck (YES/NO + Score: + evidence)
Format:
SelfCheck:
- PlanComplete: YES/NO | Score: 0/1 | Evidence: "..."
- GroundedTestMapping: YES/NO | Score: 0/1 | Evidence: "..."

Constraints:
- Do NOT invent filenames, tests, or endpoints beyond what is shown.
- Keep the plan minimal and localized.