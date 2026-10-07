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




\### 4.0 VulType



VulType: R08



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: Flask API route/controller for `POST /api/courses/<course\_id>/uploads`

\* MISSING: Upload handling utility (filename sanitization + safe path join)

\* MISSING: Upload model/repository writing to `upload` table (`original\_name`, `storage\_path`)

\* MISSING: App config initialization where `UPLOAD\_FOLDER` is defined (used to build the final stored path)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: handler for `POST /api/courses/<course\_id>/uploads` showing how it reads `request.files\['file']`, uses `file.filename`, chooses `original\_name`, builds `storage\_path`, and saves to disk.



File: MISSING



\* Needed: the code that inserts the upload record into DB table `upload` (must set `original\_name` and `storage\_path`).



File: MISSING



\* Needed: the code that defines/uses `current\_app.config\["UPLOAD\_FOLDER"]` and how file save path is constructed (to ensure storage is under upload folder and not absolute).



---



\### 4.3 RootCause



RootCause:



\* The upload endpoint likely uses the raw client-supplied filename (`../../malicious.txt`) without sanitizing it, so `original\_name` becomes `../../malicious.txt` (or includes path segments) instead of `malicious.txt`.

\* The `storage\_path` is likely derived from the unsanitized filename (or uses direct path join), so it can contain `..` segments and/or become an absolute path, failing the checks (`".." not in sp`, `not os.path.isabs(sp)`).

\* Enforcement must occur inside the \*\*upload controller/handler\*\* at the moment the filename is accepted and the disk path is computed (before writing DB row and before saving the file).



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Uploads API controller/route\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/uploads`

&nbsp; Change: Sanitize `file.filename` by extracting only the basename (drop any directory components) and normalizing it so `"../../malicious.txt"` becomes `"malicious.txt"`. Persist `upload.original\_name = sanitized\_basename`.



\* Target File: \*\*Upload storage path builder\*\* (MISSING)

&nbsp; Target: function that computes `storage\_path` and filesystem save destination

&nbsp; Change: Generate a safe \*relative\* `storage\_path` (e.g., `<course\_id>/<upload\_id>\_<safe\_name>` or random UUID + extension). Ensure it never contains `..` and is never absolute. Compute `full\_path = os.path.join(UPLOAD\_FOLDER, storage\_path)` and enforce that the resolved path stays within `UPLOAD\_FOLDER`.



\* Target File: \*\*Upload DB repository/model\*\* (MISSING)

&nbsp; Target: insert into `upload` table

&nbsp; Change: Store the sanitized `original\_name` and the safe relative `storage\_path` exactly as used for saving; do not store user-supplied raw filename/path.



\* Target File: \*\*App config\*\* (MISSING)

&nbsp; Target: `UPLOAD\_FOLDER` configuration

&nbsp; Change: Ensure it is a real directory that exists (or is created on startup) so `os.path.exists(full\_path)` passes after saving.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: uploads API controller (`POST /api/courses/<course\_id>/uploads`) → sanitize filename to basename; reject/normalize traversal names; store `original\_name="malicious.txt"`

\* MISSING: storage/path helper → create safe relative `storage\_path` under upload folder; block `..`/absolute paths; save file to `UPLOAD\_FOLDER/storage\_path`

\* MISSING: upload repo/model → persist sanitized `original\_name` + safe `storage\_path` matching the on-disk save

\* MISSING: app config/init → ensure `UPLOAD\_FOLDER` exists and is used consistently for saving and later verification



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