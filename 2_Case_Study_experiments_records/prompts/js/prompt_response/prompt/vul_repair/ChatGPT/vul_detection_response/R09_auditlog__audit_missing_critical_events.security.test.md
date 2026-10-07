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

### 4.0 VulType

VulType: R09

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Auth/login route/controller for `POST /api/auth/login` (where failed login occurs)
* MISSING: Audit logging module / repository (where audit events are inserted)
* MISSING: Admin audit API route/controller for `GET /api/admin/audit` (where logs are retrieved)
* MISSING: DB schema/init for audit log table (e.g., `audit_log` / `audit_events`) used by the audit API

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the `POST /api/auth/login` handler showing how authentication failure is handled (status 400/401/403) and whether an audit event is written on failure

File: MISSING

* Needed: the audit insert function (e.g., `logAuditEvent({ action, actor_user_id, ip, meta })`) and how it is called (or not called) from login failure path

File: MISSING

* Needed: the `GET /api/admin/audit` handler showing how audit events are queried and serialized into `{ audit: [...] }`

File: MISSING

* Needed: the audit table definition / DB setup ensuring events persist across requests in the same test DB

---

### 4.3 RootCause

RootCause:

* The failed login path in `POST /api/auth/login` likely returns 400/401/403 **without recording an audit event**, so the audit log lacks `login_failed`/`auth_failed`/`failed_login`.
* Alternatively, an event may be written but with an action name that does not match expected keywords, or it’s written to a different sink/table not exposed by `/api/admin/audit`.
* Enforcement must occur in the **login controller failure branch** (immediately after determining credentials are invalid) and be retrievable via the **admin audit API** using the same DB connection.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Auth controller** (MISSING)
  Target: `POST /api/auth/login` (failure branch when password is wrong)
  Change: On authentication failure, insert an audit event with an `action` string containing one of: `login_failed` / `auth_failed` / `failed_login` (pick one canonical, e.g., `login_failed`). Include minimal metadata (username attempted, ip, timestamp). Do this before sending the 400/401/403 response.

* Target File: **Audit logging repository/module** (MISSING)
  Target: function that inserts audit events
  Change: Ensure it writes to the same persistent DB used by `makeAppWithFreshDb` (not console-only logging). Guarantee fields required by the audit API (`action`, `created_at`, optional `actor_user_id`) exist.

* Target File: **Admin audit API controller/route** (MISSING)
  Target: `GET /api/admin/audit`
  Change: Query audit events from the audit table and return JSON shape `{ audit: [...] }` where each row includes `action`. Ensure it includes failed-login events, ordered newest-first.

* Target File: **DB schema/init** (MISSING)
  Target: audit table creation
  Change: Ensure an audit table exists and is created for fresh DBs (migration/seed). Include columns at least: `audit_id`, `action`, `created_at`, plus optional `actor_user_id`, `ip`, `meta_json`.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: auth login controller (`POST /api/auth/login`) → add audit event write on failed login with action `login_failed` (or `auth_failed`)
* MISSING: audit logger/repo → persist audit events to DB table used by the app/test harness
* MISSING: admin audit route/controller (`GET /api/admin/audit`) → return `{ audit: [...] }` including `action` values from DB
* MISSING: DB schema/init for audit table → ensure audit table exists in fresh DB and supports storing/retrieving failed-login events


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