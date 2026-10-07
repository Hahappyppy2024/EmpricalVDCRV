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


4.0 VulType
VulType: R05

4.1 RelatedFiles
- MISSING: Main application entry file (e.g., app.js or server.js) where the Express app is instantiated

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Express application configuration and middleware setup

4.3 RootCause
- The application framework (likely Express.js) includes the X-Powered-By header by default.
- The application configuration is missing the directive to disable this header, resulting in information leakage regarding the server technology.

4.4 ActionablePlan
- Target File: app.js (or main entry file)
  Target: Express app initialization
  Change: Disable the X-Powered-By header immediately after creating the app instance (e.g., `app.disable('x-powered-by')`).

4.5 FileToActionMap
- app.js → Disable the X-Powered-By response header

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