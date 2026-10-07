\[1] VulnType

VulnType: R06



\[2] TestMapping

TestMapping:



\* (expected: authenticated `POST /api/courses` with `Content-Type: application/json` and malformed body `{"title":` returns \*\*400/415/422\*\*) -> (observed: status is \*\*not\*\* in `(400, 415, 422)` (commonly 500)) -> (code cause: JSON parse failure is not handled as a client error before the `/api/courses` route/controller executes or before Flask returns a response).

\* (expected: error body must not match leakage patterns like `Traceback`, `Werkzeug`, `File "....py"`, `sqlite3.OperationalError`, or `SECRET\_KEY`) -> (observed: response text matches one of these patterns) -> (code cause: debug/error handling is returning the default Werkzeug/Flask stack trace page or propagating raw exception messages from JSON parsing / DB layer into the response).



\[3] RootCause

RootCause:



\* The API does not have a controlled handler for malformed JSON / bad request bodies, so invalid JSON triggers an unhandled exception (or Werkzeug debug page) instead of a sanitized 4xx response.

\* Global exception handling is not hardened: internal exception details (stack traces, file paths, sqlite errors, or config secrets) are being exposed in the error response body.



\[4] RepairPlan

RepairPlan:



\* Target: request parsing in the `/api/courses` handler (the code that reads JSON body) | Change: parse JSON in a “silent/guarded” way; if parsing fails or required keys are missing, return a controlled \*\*400\*\* (or \*\*422\*\*) JSON error without including exception text.

\* Target: app-wide error handlers (Flask `errorhandler` / exception middleware) | Change: add/ensure a handler for \*\*BadRequest / JSON decode errors\*\* that returns \*\*400\*\* with a generic message and no traceback/paths; similarly sanitize unexpected exceptions to a generic \*\*500\*\* without debug details.

\* Target: Flask configuration used in test environment | Change: ensure debug mode / debugger pages are disabled for API responses so Werkzeug traceback content is never returned to clients.

\* Target: DB error propagation (if `/api/courses` touches DB on failure paths) | Change: avoid executing DB writes when JSON is invalid; if any DB error occurs, return a generic error message without sqlite exception names.



\[5] ReliabilityClaim

ReliabilityClaim: The test sends malformed JSON to `POST /api/courses` and fails only if the status is not a 4xx or if the body matches explicit leak regexes (Traceback/Werkzeug/.py paths/sqlite/SECRET\_KEY), which directly indicates missing JSON-parse error handling and unsanitized exception responses in the API.



\[6] RegressionRisks

RegressionRisks:



\* Some clients may currently rely on verbose error messages for debugging; after sanitization they will receive generic errors—recheck any tests expecting specific error strings.

\* Centralized error handling might change status codes (400 vs 422) across other endpoints; recheck API consistency for validation errors.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan enforces both required properties: 4xx on malformed JSON and removal of all leak patterns listed in `\_no\_leak`."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference the exact failing call (`POST /api/courses` with malformed JSON) and the exact forbidden leak patterns asserted by the test."



