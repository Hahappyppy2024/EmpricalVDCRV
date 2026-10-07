
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
Secure file upload handling: uploaded filenames must be sanitized 
to prevent path traversal (e.g., ../../malicious.txt stored as malicious.txt 
with a safe relative storage path inside the upload folder).


### 3) Failed Security Test
import os
import io
import re

from flask import current_app
from .._helpers import db_one, api_get

# ============================================================
# API (JSON) - Upload Security
# ============================================================

def test_api_upload_path_traversal_sanitized(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    data = {"file": (io.BytesIO(b"malicious"), "../../malicious.txt")}
    rv = client.post(
        f"/api/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
        follow_redirects=False
    )
    if rv.status_code == 404:
        # API upload route not implemented
        return

    assert rv.status_code in (200, 201, 302, 400)

    upload = db_one(client, "SELECT * FROM upload ORDER BY upload_id DESC LIMIT 1")
    assert upload is not None
    assert upload["original_name"] == "malicious.txt"
    sp = upload["storage_path"]
    assert ".." not in sp
    assert not os.path.isabs(sp)

    with client.application.app_context():
        upload_folder = current_app.config["UPLOAD_FOLDER"]
    full_path = os.path.join(upload_folder, sp)
    assert os.path.exists(full_path)

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