

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)

Secure file download responses: the upload download endpoint must serve files 
as application/octet-stream and include X-Content-Type-Options: nosniff to prevent MIME sniffing.


### 3) Failed Security Test

import io
from common import api_login_admin, api_create_course, BASE_URL


def test_download_sets_nosniff_header():
    admin = api_login_admin()
    course = api_create_course(admin, title='nosniff-course')

    files = {'file': ('note.html', io.BytesIO(b'<h1>hi</h1>'), 'text/html')}
    r = admin.post(f"{BASE_URL}/api/courses/{course['course_id']}/uploads", files=files, timeout=10)
    assert r.status_code == 200, r.text
    up = r.json()['upload']

    r = admin.get(f"{BASE_URL}/api/courses/{course['course_id']}/uploads/{up['upload_id']}/download", timeout=10)
    assert r.status_code == 200

    # Content-Type is forced to octet-stream in the app; keep verifying it.
    assert r.headers.get('Content-Type', '').lower().startswith('application/octet-stream')

    # Recommended hardening header (missing => potential sniffing issues in some contexts).
    assert r.headers.get('X-Content-Type-Options', '').lower() == 'nosniff'


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