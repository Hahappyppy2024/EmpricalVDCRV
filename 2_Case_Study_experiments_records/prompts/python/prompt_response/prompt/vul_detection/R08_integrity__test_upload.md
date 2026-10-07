
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