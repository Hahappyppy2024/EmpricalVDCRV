
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


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



### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>