### 4.0 VulType

VulType: R02

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Express app bootstrap where session middleware is configured (e.g., `app.js` / `server.js` / `src/app.js`)
* MISSING: Auth/login route/controller that authenticates `/api/login` (or `/api/auth/login`) and establishes the session
* MISSING: Session middleware configuration (e.g., `express-session` setup) or any custom cookie-setting logic
* MISSING: API router for `/api/courses` (used as the probe to confirm login worked and potentially sets cookie)
* MISSING: `makeAppWithFreshDb` implementation (to know how the app is instantiated in tests and whether cookie options differ in test mode)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the session middleware setup (look for `express-session` or any `res.cookie(...)` usage), including cookie flags such as `httpOnly` and `sameSite`

File: MISSING

* Needed: login handler for `POST /api/login` or `POST /api/auth/login` showing how the session is created (e.g., `req.session.user = ...`), and whether it relies on session cookies or token auth

File: MISSING

* Needed: `makeAppWithFreshDb(...)` app factory code to confirm whether it disables/overrides cookie flags in test environment (e.g., `cookie: { ... }` options differ under `NODE_ENV=test`)

File: MISSING

* Needed: `/api/courses` route chain to confirm authentication is session-based (and not, e.g., JWT-only)

---

### 4.3 RootCause

RootCause:

* The app is not emitting a session cookie after login (no `Set-Cookie` observed), which usually means **session middleware is missing/misconfigured** or the app uses **token-based auth** instead of cookie-backed sessions (but the test expects a cookie).
* If a session cookie is set, it likely lacks required attributes because `cookie.httpOnly` and/or `cookie.sameSite` are not configured (or are overwritten in test mode).
* Enforcement must be done at the **session middleware configuration level** (global), not per-route; otherwise cookies can be inconsistently flagged depending on which handler sets them.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Express app bootstrap / app factory** (MISSING)
  Target: session middleware initialization (e.g., `app.use(session({...}))`)
  Change: Ensure the app uses cookie-based sessions and sets cookie attributes: `cookie: { httpOnly: true, sameSite: 'lax' (or 'strict') }`. Apply globally so any session cookie emitted includes these flags.

* Target File: **Login route/controller** (MISSING)
  Target: `POST /api/login` (or `POST /api/auth/login`)
  Change: On successful authentication, ensure a server-side session is actually created (e.g., assign a value under `req.session`), so Express emits `Set-Cookie`. If login currently returns success without touching `req.session`, the cookie may never be set.

* Target File: **makeAppWithFreshDb implementation** (MISSING)
  Target: test-mode app construction
  Change: Ensure test configuration does not disable cookies or strip attributes (e.g., avoid `cookie: {}` defaults or environment-based overrides that remove `sameSite`/`httpOnly`). Confirm `trust proxy` / `secure` settings don’t prevent cookie emission in the test environment.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app bootstrap/app factory → configure session middleware cookie flags (`httpOnly`, `sameSite`) globally
* MISSING: login controller/route → ensure successful login writes to `req.session` so `Set-Cookie` is emitted
* MISSING: `makeAppWithFreshDb` / test app factory → ensure test environment doesn’t suppress or alter session cookie flags
