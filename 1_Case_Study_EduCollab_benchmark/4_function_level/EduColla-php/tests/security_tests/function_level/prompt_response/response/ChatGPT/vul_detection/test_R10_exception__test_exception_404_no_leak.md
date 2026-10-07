\[1] VulnType

VulnType: R06



\[2] TestMapping

TestMapping:



\* (expected: `GET /api/\_\_definitely\_not\_a\_real\_route` returns 404/400/405 \*\*without\*\* leaking stack traces/paths) -> (observed: response body matches a leak regex such as `stack trace`, `traceback`, `at ...(:\\d+)`, `in /var/`, or PHP notice/warning patterns) -> (code cause: the app’s default 404/route-miss handling (or debug/error page) returns verbose framework/runtime output instead of a sanitized error body).

\* (expected: `POST /api/auth/login` with `Content-Type: application/json` and body `{not-json` returns an allowed status (400/401/403/415/422/500) \*\*without\*\* leaking internals) -> (observed: response body contains leaked exception details like “uncaught exception”, “traceback”, file paths, or line numbers) -> (code cause: JSON parse/validation errors propagate to the client via default exception handler or error middleware that renders raw exception text / stack details).



\[3] RootCause

RootCause:



\* API error paths are not sanitized: unknown routes and malformed JSON requests are handled by default framework/runtime error pages or unguarded exception propagation that includes internal details (stack traces, file paths, line numbers, DB exception names).

\* The correct enforcement point is the global error/404 handler (API-wide) and JSON parsing error handling for `/api/auth/login`, ensuring all error responses are controlled and non-verbose.



\[4] RepairPlan

RepairPlan:



\* Target: global 404 handler for `/api/\*` routes (route-miss handling) | Change: return a minimal JSON error body (or minimal text) for unknown API routes with status 404, avoiding any framework/debug “Cannot GET” pages that may include stack/path details.

\* Target: global exception/error middleware (app-wide) | Change: ensure exceptions are caught and responses are sanitized (no stack traces, no file paths, no raw exception messages); return generic messages for 5xx and generic validation messages for 4xx.

\* Target: JSON request parsing for `POST /api/auth/login` | Change: add a malformed-JSON guard so JSON decode failures return \*\*400/415/422\*\* with a generic “Invalid JSON” style message and no exception details.

\* Target: configuration affecting error verbosity (debug/dev settings) | Change: disable debug/error detail rendering for API responses so production-style error bodies are used in this test environment.



\[5] ReliabilityClaim

ReliabilityClaim: The test fails only when the bodies of an unknown API route response or a malformed-JSON login response match explicit leak patterns (stack traces/paths/exception text), which directly indicates unsanitized 404 and exception handling on API error paths.



\[6] RegressionRisks

RegressionRisks:



\* If any tests/tools currently rely on detailed error strings for debugging, sanitization will change those bodies; recheck error-message assertions elsewhere.

\* A global JSON-only 404 handler for `/api/\*` might alter content-type for some non-JSON API endpoints; recheck compatibility for endpoints that intentionally return HTML.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan addresses both failing surfaces in the test: unknown API route error handling and malformed JSON handling for `/api/auth/login`, ensuring neither leaks internals."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference exactly the two requests made by the test and the specific leak regex families it checks (traceback/stack/paths/PHP notices)."



