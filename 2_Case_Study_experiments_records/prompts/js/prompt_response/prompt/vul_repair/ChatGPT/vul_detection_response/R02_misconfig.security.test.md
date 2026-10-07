You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
Secure configuration: the application must not disclose the X-Powered-By HTTP response header on any route, reducing information leakage.

### 3) Failed Security Test

const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R02 misconfig headers v1');

describe('Security: Misconfiguration', () => {
  test('does not expose X-Powered-By header', async () => {
    const { agent } = makeAppWithFreshDb('sec_misconfig_hdr.db');

    // Use any route; allow 200/302/404 depending on app
    const res = await agent.get('/');

    expect([200, 302, 404]).toContain(res.status);
    // Header name is case-insensitive; supertest normalizes to lowercase keys
    expect(res.headers['x-powered-by']).toBeUndefined();
  });
});


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


Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"