You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

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

4.0 VulType

VulType: R01



4.1 RelatedFiles

\- MISSING: Route handler for POST /api/courses/<course\_id>/uploads

\- MISSING: File storage utility or logic



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic processing the uploaded file and determining the storage path



4.3 RootCause

\- The file upload logic fails to sanitize the user-provided filename, allowing directory traversal sequences (../) to be passed to the file system or database.

\- The storage path is constructed using the raw filename without validating or resolving the absolute path, potentially allowing files to be written outside the designated upload folder.



4.4 ActionablePlan

\- Target File: routes/upload\_routes.py (or controller)

&nbsp; Target: Upload handler function

&nbsp; Change: Sanitize the filename using `os.path.basename` (or equivalent) to strip directory information. When saving, generate a safe storage path (e.g., using a UUID or timestamp) or strictly validate that the resolved path is within the allowed `UPLOAD\_FOLDER`.



4.5 FileToActionMap

\- routes/upload\_routes.py → Implement filename sanitization and path validation.

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