\[1] VulnType

VulnType: R08



\[2] TestMapping

TestMapping:



\* (expected: `POST /api/courses/:course\_id/uploads` with filename `../../malicious.txt` succeeds or returns 400, but DB row’s `original\_name` is sanitized to `malicious.txt`) -> (observed: latest `upload.original\_name` is not `malicious.txt` (likely keeps `../../malicious.txt` or another unsafe value)) -> (code cause: upload handler stores `file.filename` directly into `upload.original\_name` without sanitizing/basename extraction).

\* (expected: `upload.storage\_path` is a safe relative path (no `..`, not absolute) and points to an existing file inside `UPLOAD\_FOLDER`) -> (observed: `storage\_path` contains `..` and/or is absolute, or `os.path.exists(join(UPLOAD\_FOLDER, storage\_path))` fails) -> (code cause: upload handler constructs `storage\_path` from the untrusted filename or uses unsafe path joining, allowing traversal segments or producing a path that doesn’t map to the saved file).



\[3] RootCause

RootCause:



\* The upload endpoint (`POST /api/courses/:course\_id/uploads`) does not sanitize the client-supplied filename before persisting it and/or using it to compute a filesystem destination path.

\* The stored `storage\_path` is not guaranteed to be a relative, traversal-free path under `UPLOAD\_FOLDER` (missing basename normalization and/or missing “final path must stay within upload dir” check).



\[4] RepairPlan

RepairPlan:



\* Target: controller/handler for `POST /api/courses/:course\_id/uploads` | Change: sanitize the uploaded filename by stripping directories (take basename) and normalizing it so `../../malicious.txt` becomes exactly `malicious.txt` before writing `upload.original\_name`.

\* Target: same handler’s filesystem save logic | Change: generate a safe `storage\_path` that is always relative (e.g., `<course\_id>/<generated\_id>\_malicious.txt` or similar) and never derived by direct concatenation with the raw filename; ensure it contains no `..` and is not absolute.

\* Target: same handler’s path-join validation | Change: after joining `UPLOAD\_FOLDER` + `storage\_path`, verify the resolved absolute path is still inside `UPLOAD\_FOLDER`; if not, reject with \*\*400\*\* and do not insert the DB row.

\* Target: DB insert/update for the `upload` record | Change: persist `original\_name = sanitized\_basename` and `storage\_path = safe\_relative\_path` that matches the actually saved file location so the existence check passes.



\[5] ReliabilityClaim

ReliabilityClaim: The test uploads a file named `../../malicious.txt` and then asserts the DB row stores `original\_name == "malicious.txt"`, `storage\_path` is relative and traversal-free, and the file exists under `UPLOAD\_FOLDER`, so failure directly indicates missing filename sanitization and/or unsafe storage path construction in the upload handler.



\[6] RegressionRisks

RegressionRisks:



\* If you previously relied on preserving full client filenames (including subpaths), sanitization will change displayed names; recheck any UI that shows upload names.

\* If existing uploads have unsafe/legacy `storage\_path` values, adding strict validation may break downloads for those records; recheck backward compatibility or add migration/guard logic.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan enforces the exact post-conditions asserted: basename `malicious.txt`, relative traversal-free `storage\_path`, and file saved within `UPLOAD\_FOLDER`."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only the tested endpoint `/api/courses/:course\_id/uploads` and the concrete DB fields asserted (`upload.original\_name`, `upload.storage\_path`) plus the `UPLOAD\_FOLDER` existence check."



