

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
Safe error responses: API error conditions (e.g., unknown routes or
malformed JSON) must return controlled status codes without leaking 
internal implementation details such as stack traces, file paths, or exception messages.


### 3) Failed Security Test


import re
import pytest
import requests
from common import BASE_URL


LEAK_PATTERNS = [
    r"fatal error",                 # PHP fatal
    r"uncaught .*exception",        # PHP uncaught
    r"pdoexception",                # DB exception
    r"stack trace",                 # generic stack trace
    r"traceback \(most recent call last\)",  # Python traceback
    r"\bat\s+\w.*\(.+:\d+\)",       # Java-ish stack lines
    r"in\s+/var/",                  # unix paths
    r"in\s+/home/",                 # unix paths
    r"in\s+c:\\",                   # windows paths
    r"undefined (index|variable)",  # PHP notices
    r"\bnotice:\b",                 # PHP notice prefix
    r"\bwarning:\b",                # PHP warning prefix (more specific than 'warning' alone)
    r"on line \d+",                 # PHP line leak
]


def _assert_no_leak(resp: requests.Response):
    body = resp.text or ""
    lowered = body.lower()
    for pat in LEAK_PATTERNS:
        if re.search(pat, lowered):
            pytest.fail(
                "Internal detail leaked in error response.\n"
                f"status={resp.status_code} ct={resp.headers.get('Content-Type')}\n"
                f"matched={pat}\n"
                f"body_head={(body[:600])!r}"
            )


def test_error_responses_do_not_leak_internal_details():
    s = requests.Session()

    # 1) Unknown API route (commonly 404)
    r1 = s.get(f"{BASE_URL}/api/__definitely_not_a_real_route", timeout=10, allow_redirects=False)
    assert r1.status_code in (404, 400, 405), f"Unexpected status={r1.status_code} body_head={(r1.text or '')[:200]!r}"
    _assert_no_leak(r1)

    # 2) Malformed JSON to a known JSON endpoint (if exists) to trigger error handling paths.
    # Some apps may return 400/415/422/500; we only assert "no internal leak".
    r2 = s.post(
        f"{BASE_URL}/api/auth/login",
        data="{not-json",
        headers={"Content-Type": "application/json"},
        timeout=10,
        allow_redirects=False,
    )
    assert r2.status_code in (400, 401, 403, 415, 422, 500), (
        f"Unexpected status={r2.status_code} body_head={(r2.text or '')[:200]!r}"
    )
    _assert_no_leak(r2)

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