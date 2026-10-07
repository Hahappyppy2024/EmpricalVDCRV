### 4.0 VulType

VulType: R02

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Express app bootstrap / server entry that constructs the `express()` app (where `X-Powered-By` is enabled by default)
* MISSING: Test helper that creates the app instance used by `makeAppWithFreshDb` (to locate where to apply the header hardening globally)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the `express()` app creation code (e.g., `const app = express();`) and any security/header middleware configuration near initialization (place to call `app.disable('x-powered-by')`)

File: MISSING

* Needed: the implementation of `makeAppWithFreshDb(...)` to identify which app module is being imported and where the Express app is configured (so the fix affects all routes)

---

### 4.3 RootCause

RootCause:

* Express exposes the `X-Powered-By: Express` header by default unless explicitly disabled.
* The app initialization code does not disable this header globally (missing `app.disable('x-powered-by')` or equivalent).
* Because the test hits `/` (any route), the fix must be applied at the **app bootstrap level**, not per-route.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Express app entry / initialization module** (MISSING)
  Target: right after `const app = express()` (before routes are mounted)
  Change: Disable the header globally (e.g., call `app.disable('x-powered-by')`) so no route returns `X-Powered-By`.

* Target File: **`makeAppWithFreshDb` helper import path** (MISSING: `tests/_helpers.js` already calls it, but actual app module path is unknown)
  Target: ensure the helper uses the same app initialization module (not a separate “test app” that re-enables defaults)
  Change: Verify the test harness is instantiating the hardened app; if it builds its own Express instance, apply the same disable step there as well.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app bootstrap file (creates `express()` app) → disable `x-powered-by` globally during app initialization
* MISSING: `makeAppWithFreshDb` implementation / test app factory → ensure the factory returns the hardened app instance (and disables `x-powered-by` if it constructs a separate app)
