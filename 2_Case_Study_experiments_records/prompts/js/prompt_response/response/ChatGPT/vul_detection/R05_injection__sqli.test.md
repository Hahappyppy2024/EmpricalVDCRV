### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API router that defines `GET /api/courses/:course_id/search/posts`
* MISSING: Search controller/handler for post search (parses `keyword` query param)
* MISSING: Data access layer / repository that queries posts for a course with a keyword filter (SQL construction point)
* MISSING: Global error handler / response shaping for API exceptions (to prevent sqlite/stack leakage)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route definition for `GET /api/courses/:course_id/search/posts` including any middleware chain and handler name

File: MISSING

* Needed: the search handler code that reads `req.query.keyword` and executes a DB query (this is where SQLi payloads may break query building)

File: MISSING

* Needed: the DB query code that searches posts (look for string-interpolated SQL such as `... WHERE ... LIKE '%${keyword}%'` or concatenation)

File: MISSING

* Needed: API error-handling code path that converts DB errors into a controlled `400` (or safe `200` with empty results) without exposing sqlite errors/stack traces in body

---

### 4.3 RootCause

RootCause:

* The search implementation likely builds SQL using **string concatenation/interpolation** with the raw `keyword` (e.g., `... LIKE '%${keyword}%'`), so a payload like `' OR 1=1 --` triggers a sqlite syntax error or changes query semantics.
* The handler does not catch DB exceptions and/or the app’s error handler returns raw error messages/stack traces (body contains “sqlite”, “syntax error”, “select … from”, “stack/traceback”), failing the “no leak” assertion.
* Enforcement belongs at the **data access layer** (parameterized query) and at the **controller/global error boundary** (return only controlled 200/400 with sanitized error body).

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: handler for `GET /api/courses/:course_id/search/posts`
  Change: Validate `keyword` is a string and enforce basic bounds (e.g., max length). Wrap DB call in try/catch; on error return **400** with a generic message (no sqlite/SQL text).

* Target File: **Posts search repository / DB module** (MISSING)
  Target: function that queries posts by course + keyword
  Change: Replace any string-concatenated SQL with a **parameterized query** (placeholders) and bind `%keyword%` as a parameter; never embed keyword directly into SQL text.

* Target File: **Global API error handler** (MISSING)
  Target: Express error middleware (or per-route error handling)
  Change: Ensure unexpected exceptions return a controlled status (400/500 as appropriate) with a **sanitized JSON error** and without leaking DB engine strings, SQL fragments, stack traces, or file paths.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller file → validate keyword input; catch DB errors; respond with safe 200/400 and non-leaky body
* MISSING: posts search DB/repository file → parameterize keyword in SQL (no interpolation) to prevent SQLi/syntax crashes
* MISSING: global error handler/app bootstrap → sanitize exception responses so sqlite/stack details are never returned in API bodies
