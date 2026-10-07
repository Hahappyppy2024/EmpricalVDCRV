### 4.0 VulType

VulType: R08

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Uploads API router that defines `GET /api/courses/:course_id/uploads/:upload_id/download`
* MISSING: Upload download controller/handler (the code that maps `upload_id` → file path and serves it)
* MISSING: Uploads repository/model that fetches upload metadata (stored filename/path) by `upload_id`
* MISSING: File storage helper (where upload directory is defined and paths are joined/resolved)
* MISSING: Express app configuration for route params / decoding behavior (to confirm how `../../app.js` reaches the handler)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the exact route definition for download, including whether `:upload_id` is constrained (e.g., `:upload_id(\\d+)`) and what handler it calls

File: MISSING

* Needed: the download handler code that reads `req.params.upload_id` and constructs the filesystem path (look for `path.join`, `sendFile`, `res.download`, `fs.createReadStream`)

File: MISSING

* Needed: the upload metadata lookup by id (SQL query or in-memory mapping). This determines whether traversal strings are ever used as file paths directly.

File: MISSING

* Needed: the storage path helper (base upload directory). This is where path traversal must be prevented (ensure resolved path stays under base dir).

---

### 4.3 RootCause

RootCause:

* `upload_id` is not strictly validated, so a traversal payload like `../../app.js` can reach the download logic instead of being rejected with **400**.
* The download handler likely uses `upload_id` (or a derived value) in filesystem path construction without enforcing an **ID-only contract** (e.g., numeric/UUID) and/or without checking that the resolved path stays within the uploads directory.
* Enforcement must occur at the **route/controller boundary** (validate `upload_id` format early) and defensively at the **file-serving boundary** (resolve + “must stay under base dir” check).

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Uploads download route file** (MISSING)
  Target: route definition for `GET /api/courses/:course_id/uploads/:upload_id/download`
  Change: Constrain `:upload_id` to an allowed strict pattern (preferably numeric id `^\d+$` or a UUID pattern if that’s what you use). If it doesn’t match, immediately return **400**.

* Target File: **Uploads download controller/handler** (MISSING)
  Target: download handler (e.g., `downloadUpload`)
  Change: Before any DB/file action, validate `upload_id` strictly; on invalid format return **400** with JSON error (and ensure response content-type is not a file type). Do not treat `upload_id` as a path segment.

* Target File: **File storage helper / path join logic** (MISSING)
  Target: function that builds the absolute file path to serve
  Change: Use `path.resolve(baseDir, relativeNameFromDb)` and verify the resolved path starts with `baseDir` (or is within it). If it escapes, return **400/403** and never call `sendFile/download`.

* Target File: **Uploads repository/model** (MISSING)
  Target: `getUploadById(course_id, upload_id)`
  Change: Ensure lookup is by id via parameterized query; never accept filename/path from user input. If no record found, return **404** (but invalid-format must remain **400**).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: uploads routes file → add strict param validation (pattern constraint) and return 400 on invalid `upload_id`
* MISSING: uploads download controller → validate `upload_id` before DB/path usage; reject traversal payloads with 400
* MISSING: storage/path helper → enforce “resolved path stays under uploads base dir” before serving any file
* MISSING: uploads repo/model → ensure upload lookup is id-based and parameterized; no user-controlled path usage
