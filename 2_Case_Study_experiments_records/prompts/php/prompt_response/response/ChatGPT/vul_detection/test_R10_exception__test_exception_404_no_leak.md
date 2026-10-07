### 4.0 VulType

VulType: R10

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: global error handler / exception handler for API routes (controls 4xx/5xx body)
* MISSING: router/app bootstrap where 404/405 are produced for unknown `/api/*` routes
* MISSING: login route/controller for `POST /api/auth/login` (JSON parsing + error handling path)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the code that handles unknown routes under `/api/*` (404 handler) and what body it returns (must confirm it does not include stack traces / file paths).

File: MISSING

* Needed: the JSON parsing layer for `POST /api/auth/login` (where malformed JSON triggers an exception) and the handler that formats the error response.

File: MISSING

* Needed: any global “debug” / “development mode” settings that cause detailed exception pages (stack traces, file paths) to be rendered.

---

### 4.3 RootCause

RootCause:

* Malformed JSON sent to `POST /api/auth/login` is likely causing an unhandled JSON parse exception (or framework default error page), resulting in a **500** with leaked details (stack traces / file paths / exception text) that match the test’s `LEAK_PATTERNS`.
* Unknown `/api/*` routes may be returning a default framework 404/405 body (or a debug page) that includes internal details such as “Cannot …” pages, stack traces, or path info (especially if debug is enabled).
* Missing or incorrect **global error handling**: no centralized handler that converts exceptions (JSON decode errors, routing errors) into a controlled JSON/plain response without internal details.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: MISSING (app/bootstrap where middleware and error handlers are registered)
  Target: global API error-handling layer (before/after routes)
  Change: Add a centralized error handler for `/api/*` that:

  * catches JSON parse errors / bad request exceptions and returns **400** (or **415/422**) with a minimal body (e.g., `{"error":"bad_request"}`) **without** exception messages/stack/path.
  * catches generic exceptions and returns **500** with a generic body (e.g., `{"error":"internal_error"}`) **without** any internal details.
  * ensures Content-Type is consistent (JSON for API).

* Target File: MISSING (route/controller for `POST /api/auth/login`)
  Target: login handler JSON parsing
  Change: Ensure the handler safely reads JSON:

  * If request body is not valid JSON (decode error), return **400** immediately (no throw).
  * Do not echo back parsing exceptions in the response.

* Target File: MISSING (routing for unknown `/api/*` endpoints)
  Target: 404/405 handler for `/api/*`
  Change: Override default 404/405 bodies for API paths to a minimal response (JSON or empty) and ensure debug pages are never served for `/api/*`.

* Target File: MISSING (config)
  Target: debug/error-display configuration
  Change: Disable debug traceback rendering in the test/runtime environment (ensure production-style error pages), so stack traces/file paths never appear in responses.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app/bootstrap error handler registration → add centralized `/api/*` exception + 404/405 handlers returning sanitized bodies and controlled 4xx/5xx codes
* MISSING: `POST /api/auth/login` controller → catch malformed JSON and return 400 without leaking exception details
* MISSING: API routing/404 handler → ensure unknown API routes return minimal 404/405 without framework debug content
* MISSING: runtime/config → disable debug tracebacks / detailed error pages in responses
