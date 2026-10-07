
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)
Secure session cookie configuration: after login, the server must set the 
session cookie with HttpOnly and a valid SameSite attribute (Lax/Strict/None) to reduce XSS/CSRF risk.


### 3) Failed Security Test
import re
import pytest
import requests
from common import BASE_URL


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
    """
    Return all Set-Cookie header lines (not merged).
    requests may expose only one via resp.headers; raw headers are more reliable.
    """
    raw = getattr(resp, "raw", None)
    if raw and getattr(raw, "headers", None):
        h = raw.headers
        # urllib3 / http.client compatible
        if hasattr(h, "get_all"):
            vals = h.get_all("Set-Cookie") or []
            return [v for v in vals if v]
    # fallback (may be merged/partial)
    v = resp.headers.get("Set-Cookie")
    return [v] if v else []


def _pick_session_cookie(set_cookie_lines: list[str]) -> str | None:
    """
    Pick the session cookie line. Default PHP session cookie is PHPSESSID.
    Allow override via env if needed.
    """
    import os
    name = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")
    for line in set_cookie_lines:
        if line.lower().startswith(name.lower() + "="):
            return line
    # fallback: any cookie that looks like a session id (best effort)
    for line in set_cookie_lines:
        if re.match(r"^[A-Za-z0-9_]+=[^;]+;", line):
            if "phpsessid" in line.lower():
                return line
    return None


def test_session_cookie_has_httponly_and_samesite():
    s = requests.Session()
    r = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
    )
    assert r.status_code == 200, r.text

    set_cookies = _all_set_cookie_headers(r)
    if not set_cookies:
        pytest.skip("Cannot observe Set-Cookie headers in this environment; skip cookie-flag validation.")

    sess_cookie = _pick_session_cookie(set_cookies)
    if not sess_cookie:
        pytest.skip(f"No recognizable session cookie found in Set-Cookie headers: {set_cookies}")

    lower = sess_cookie.lower()

    # Must have HttpOnly
    assert "httponly" in lower, f"Session cookie must include HttpOnly. Got: {sess_cookie}"

    # Must have SameSite
    m = re.search(r"samesite=([a-z]+)", lower)
    assert m, (
        "Session cookie should include SameSite (Lax/Strict/None). "
        f"Got: {sess_cookie}"
    )

    samesite = m.group(1)
    assert samesite in ("lax", "strict", "none"), f"Unexpected SameSite value: {samesite}. Full cookie: {sess_cookie}"

    # If SameSite=None, Secure is strongly recommended; do not hard-fail for local HTTP,
    # but warn by failing only if you want to enforce strict policy.
    if samesite == "none" and "secure" not in lower:
        pytest.skip("SameSite=None without Secure observed; in production this should be Secure. Skipping for local HTTP.")

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