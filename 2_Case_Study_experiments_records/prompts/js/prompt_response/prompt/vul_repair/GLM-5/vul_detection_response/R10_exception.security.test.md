You are a web application security engineer (vulnerability repairer).

###1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
Error handling hardening: the API must reject malformed JSON with a controlled 400/415 response and must not leak stack traces or internal file paths in the error message.


### 3) Failed Security Test
const { makeAppWithFreshDb } = require('../_helpers');

describe('Security: Exceptional conditions', () => {
  test('malformed JSON returns 400 and does not leak stack/paths', async () => {
    const { agent } = makeAppWithFreshDb('sec_badjson.db');

    const res = await agent
      .post('/api/courses')
      .set('Content-Type', 'application/json')
      .send('{"title":'); // malformed

    expect([400, 415]).toContain(res.status); // 400 preferred
    const body = typeof res.body === 'object' ? JSON.stringify(res.body) : (res.text || '');
    expect(body).not.toMatch(/Traceback|stack|at\s|\/mnt\/|\\src\\|SyntaxError/i);
  });
});

4.0 VulType
VulType: R05

4.1 RelatedFiles
- MISSING: Main application entry file (e.g., app.js) containing global error handling middleware
- MISSING: Body parser configuration (e.g., express.json())

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Global error handler middleware and body parser setup

4.3 RootCause
- The body-parser middleware throws a SyntaxError when parsing malformed JSON, which is not caught by a specific error handler.
- The default error handling mechanism returns a 500 status code and/or includes the full stack trace and raw error message in the response, leaking internal implementation details to the client.

4.4 ActionablePlan
- Target File: app.js
  Target: Global error handling middleware
  Change: Add an error handling middleware function (with 4 arguments: err, req, res, next) that specifically checks for SyntaxError (or instance of URIError) from the body-parser and returns a generic 400 Bad Request response with a safe error message (e.g., "Invalid JSON").

- Target File: app.js
  Target: Body parser configuration
  Change: Ensure `express.json()` is used, and verify that errors from it are propagated to the global error handler.

4.5 FileToActionMap
- app.js → Add global error handler to catch and sanitize JSON parsing errors.

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