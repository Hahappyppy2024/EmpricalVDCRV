\### 4.0 VulType



VulType: R10



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: Flask app factory / main entry where JSON parsing and error handlers are configured (e.g., `app.py`, `\_\_init\_\_.py`)

\* MISSING: API route/controller for `POST /api/courses` (course creation endpoint)

\* MISSING: Global exception/error handler for API errors (Flask `@app.errorhandler(...)` / blueprint error handlers)

\* MISSING: Auth/session middleware used by `auth.login(...)` (not the failure focus but affects request path)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the `POST /api/courses` route handler showing how it reads JSON (e.g., `request.get\_json()` / `request.json`) and what happens on malformed JSON



File: MISSING



\* Needed: any global error handlers (e.g., handlers for `BadRequest`, `HTTPException`, generic `Exception`) and whether they return debug details (`repr(e)`, `e.description`, `traceback`, etc.)



File: MISSING



\* Needed: app config toggles that might enable Werkzeug debugger or verbose error pages (e.g., `DEBUG=True`, `PROPAGATE\_EXCEPTIONS`, `TRAP\_HTTP\_EXCEPTIONS`, `TESTING`)



---



\### 4.3 RootCause



RootCause:



\* Malformed JSON (`{"title":`) triggers a parsing exception (commonly Flask/Werkzeug `BadRequest` / `JSONDecodeError`). The app likely returns a \*\*500\*\* or an error body that includes \*\*Werkzeug/traceback/file paths/sqlite3 errors/SECRET\_KEY text\*\*, failing the “no leak” regex checks.

\* The JSON parsing path likely uses `request.get\_json(force=True)` or otherwise raises, and there is no hardened error handler to convert this into a controlled \*\*400/415/422\*\* with a sanitized body.

\* Enforcement must occur at the \*\*global API error-handling boundary\*\* (and optionally at the `POST /api/courses` controller) so parse errors never propagate to default debug pages or raw exception dumps.



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*App factory / bootstrap\*\* (MISSING)

&nbsp; Target: Flask app initialization

&nbsp; Change: Disable debug-style exception propagation in the test/runtime configuration (ensure `DEBUG` is off for API responses). Register hardened error handlers for JSON parse failures and generic exceptions.



\* Target File: \*\*Global error handler module\*\* (MISSING)

&nbsp; Target: `@app.errorhandler(400)` / `@app.errorhandler(BadRequest)` (and/or `@app.errorhandler(HTTPException)`)

&nbsp; Change: For malformed JSON / bad request, return \*\*400\*\* (or 415/422) with a minimal JSON body like `{"error":"invalid\_json"}` and \*\*never\*\* include traceback, Werkzeug debugger strings, file paths, sqlite exceptions, or config secrets.



\* Target File: \*\*Courses API controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses`

&nbsp; Change: Parse JSON using a safe pattern: if parsing fails, immediately return \*\*400/422\*\* with a generic message; do not log/echo exception text into the response. Ensure the handler doesn’t bubble raw exceptions upward.



\* Target File: \*\*Logging / error formatting\*\* (if present) (MISSING)

&nbsp; Target: any code that builds response bodies from exceptions

&nbsp; Change: Remove/avoid returning `str(e)`, `repr(e)`, `traceback.format\_exc()`, or config values. Keep detailed errors only in server logs (not response body).



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: app factory/bootstrap → ensure debug error pages are not exposed; register hardened error handlers

\* MISSING: global error handlers → catch malformed JSON/BadRequest and return sanitized 400/415/422 JSON without leak strings

\* MISSING: `POST /api/courses` controller → safely handle `request.get\_json()` failures and return controlled 4xx

\* MISSING: error/exception response formatter → ensure no stack traces, file paths, sqlite errors, or secrets are included in API responses



