You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)
Security auditing: failed login attempts must be recorded as audit-log events and retrievable via the admin audit API (GET /api/admin/audit).


### 3) Failed Security Test

const { makeAppWithFreshDb, apiLogin } = require('../_helpers');

describe('R09 Security: Logging for critical events', () => {
  test('failed login SHOULD be recorded in audit log (fails if missing)', async () => {
    const { agent } = makeAppWithFreshDb('sec_a09_failed_login_required.db');

    // trigger a failed login
    const bad = await agent.post('/api/auth/login')
      .send({ username: 'admin', password: 'wrong-password' });
    expect([400, 401, 403]).toContain(bad.status);

    // login successfully and fetch audit log
    const ok = await apiLogin(agent, { username: 'admin', password: 'admin123' });
    expect([200, 302]).toContain(ok.status);

    // Prefer JSON audit API. If missing, treat as FAIL for this dataset-style test.
    const r = await agent.get('/api/admin/audit');

    expect(r.status).toBe(200);
    expect(r.body && Array.isArray(r.body.audit)).toBe(true);

    const rows = r.body.audit;

    const hasFailedLogin = rows.some((row) => {
      const a = String(row.action || '').toLowerCase();
      return a.includes('login_failed') || a.includes('auth_failed') || a.includes('failed_login');
    });

    // PASS => logging exists; FAIL => missing => vulnerability exists
    expect(hasFailedLogin).toBe(true);
  });
});


4.0 VulType
VulType: R09

4.1 RelatedFiles
- MISSING: Route handler for POST /api/auth/login (or /api/login)
- MISSING: Audit logging utility or middleware
- MISSING: Route handler for GET /api/admin/audit (implied existing but relying on missing logs)

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Authentication logic handling the login failure path

File: MISSING
- Needed: Function to write audit logs to the database

4.3 RootCause
- The authentication endpoint fails to log security-critical events; specifically, it does not create an audit log entry when a login attempt fails.
- The system lacks instrumentation to record 'login_failed' events, violating the requirement to monitor and audit suspicious activities.

4.4 ActionablePlan
- Target File: controllers/authController.js (or the file handling login)
  Target: Login handler function
  Change: In the logic block where authentication fails (wrong password/user not found), invoke the audit logging service to record an event with action 'login_failed' (or similar), including the username and IP address.

- Target File: services/auditService.js
  Target: Audit logging function
  Change: Ensure the logging function supports creating entries for authentication failures and persists them to the database accessible by the audit API.

4.5 FileToActionMap
- controllers/authController.js → Add logging call on login failure.
- services/auditService.js → Ensure support for logging authentication events.

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