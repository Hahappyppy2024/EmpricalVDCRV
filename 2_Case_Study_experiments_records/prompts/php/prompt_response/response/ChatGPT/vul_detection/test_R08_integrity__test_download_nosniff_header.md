### 4.0 VulType

VulType: R02

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: route/controller for `GET /api/courses/<course_id>/uploads/<upload_id>/download`
* MISSING: upload/download controller/service that constructs the file response (where headers are set)
* MISSING: any shared response/header middleware (e.g., “security headers” / after_request hook) that could set `X-Content-Type-Options`

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the concrete handler for `GET /api/courses/<course_id>/uploads/<upload_id>/download` that returns the file response (must show how `Content-Type` and headers are set).

File: MISSING

* Needed: any global middleware / after_request hook / response wrapper that sets security headers across routes (or omits them).

---

### 4.3 RootCause

RootCause:

* The download endpoint returns the file with `Content-Type: application/octet-stream` (per test comment), but **does not add** `X-Content-Type-Options: nosniff`, so the response lacks the hardening header and the test fails.
* Header must be enforced at the **download response construction point** (best: per-download route) or via a **global response hook** that applies to all responses (but must not break existing tests).

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: MISSING (download route/controller file)
  Target: `GET /api/courses/<course_id>/uploads/<upload_id>/download` handler
  Change: When constructing the download response, explicitly set:

  * `Content-Type` to `application/octet-stream` (keep existing behavior)
  * `X-Content-Type-Options` to `nosniff`
    Ensure the header is present on the final response object returned by the handler.

* Target File: MISSING (optional global security headers middleware / after_request)
  Target: global response hook (applies to all routes)
  Change: Add `X-Content-Type-Options: nosniff` for all responses **or** at minimum for download responses. Prefer per-download change if you want minimal blast radius.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: download controller/route for uploads → add `X-Content-Type-Options: nosniff` on the file download response (keep octet-stream)
* MISSING: global response middleware/hook (optional) → enforce `X-Content-Type-Options: nosniff` consistently (or restrict to downloads)
