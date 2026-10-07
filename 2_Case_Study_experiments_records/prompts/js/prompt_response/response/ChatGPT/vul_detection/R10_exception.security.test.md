### 4.0 VulType

VulType: R10

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Express app bootstrap/app factory where JSON body parsing middleware is configured (e.g., `app.js`, `server.js`, `src/app.js`)
* MISSING: API router/controller for `POST /api/courses` (the endpoint hit by the malformed JSON)
* MISSING: Global error-handling middleware (Express error handler) that should catch body-parser JSON parse errors and sanitize responses
* MISSING: `makeAppWithFreshDb` implementation (to locate which app module is used in tests and whether error handling differs in test mode)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the JSON parsing middleware setup (e.g., `app.use(express.json(...))` or `bodyParser.json(...)`) and any custom verify/error hooks

File: MISSING

* Needed: route definition for `POST /api/courses` (to confirm request reaches the app and whether the crash/leak happens before controller)

File: MISSING

* Needed: the global Express error middleware (e.g., `app.use((err, req, res, next) => ...)`) that should convert `SyntaxError` from JSON parsing into a controlled `400/415` with a non-leaky body

File: MISSING

* Needed: `makeAppWithFreshDb(...)` to confirm the app is created with/without the error handler and whether stack traces are enabled under test

---

### 4.3 RootCause

RootCause:

* Malformed JSON triggers a JSON parsing exception (commonly a `SyntaxError`) in the JSON body parser; without a dedicated error handler, Express may return a **500** or a response body that includes **error details/stack paths** (or the app explicitly serializes `err.stack` / `err.message`).
* The app lacks (or mis-orders) a **global error-handling middleware** that catches JSON parse errors and returns **400** (or **415**) with a generic message.
* The leak occurs because the error response body includes raw exception info (keywords like `SyntaxError`, stack frames `at ...`, or file paths), violating the “no leak” requirement.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **App bootstrap / app factory** (MISSING)
  Target: JSON body parser registration (e.g., `app.use(express.json())`) and middleware ordering
  Change: Ensure JSON parsing is enabled for `/api/*` and that a dedicated error handler is registered **after** routes to catch parse errors. Do not let default handlers expose stack/paths.

* Target File: **Global error-handling middleware module** (MISSING)
  Target: Express error handler (final `app.use((err, req, res, next) => ...)`)
  Change: Detect malformed JSON errors (typically `err instanceof SyntaxError` with `err.type === 'entity.parse.failed'` or similar) and return **400** (or **415**) with a minimal JSON body like `{ error: "Invalid JSON" }` (no `err.stack`, no file paths, no exception class name).

* Target File: **Courses API route/controller** (MISSING)
  Target: `POST /api/courses`
  Change: Do not assume `req.body` exists when parsing fails; rely on the global handler. Optionally validate content-type and return **415** if non-JSON content-type is sent, but for malformed JSON with `application/json`, return **400**.

* Target File: **makeAppWithFreshDb implementation** (MISSING)
  Target: test app creation
  Change: Ensure the hardened error handler is included in the app instance used by tests (avoid “dev error handler” that echoes stack traces in test mode).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app bootstrap/app factory → ensure express JSON parser is configured and hardened error handler is mounted after routes
* MISSING: global error middleware → catch JSON parse `SyntaxError` and return controlled 400/415 with sanitized body (no stack/paths/exception details)
* MISSING: courses route/controller → rely on parse-error handler; avoid leaking errors when `req.body` invalid
* MISSING: `makeAppWithFreshDb` → ensure test app uses the same hardened middleware stack (no debug stack traces)
