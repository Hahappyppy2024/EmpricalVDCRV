You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)
Secure session management: after login, the app must set a session cookie with HttpOnly and SameSite attributes to mitigate XSS/CSRF risks.


### 3) Failed Security Test
const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R02 session cookie flags v2 (self-login)');

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

function cookieHeaderToString(setCookieHeaders) {
  const arr = Array.isArray(setCookieHeaders)
    ? setCookieHeaders
    : setCookieHeaders
    ? [setCookieHeaders]
    : [];
  return arr.join(' ');
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
  ];

  let last = null;
  for (const a of attempts) {
    const r = await agent
      .post(a.path)
      .set('Content-Type', 'application/json')
      .send(a.body);
    last = r;

    if ([200, 201, 204, 302].includes(r.status)) {
      // probe: after login, a protected endpoint should not be 401/302
      const probe = await agent.get('/api/courses');
      if (![401, 302].includes(probe.status)) return r;
    }
  }

  throw new Error(
    `LOGIN FAILED for user=${username}\n` +
      `Last response: ${JSON.stringify(short(last), null, 2)}\n` +
      `Tip: your login endpoint/payload keys may differ.`
  );
}

describe('Security: Misconfiguration (Session Cookie Flags)', () => {
  test('session cookie includes HttpOnly and SameSite', async () => {
    const { agent } = makeAppWithFreshDb('sec_misconfig_cookie.db');

    // 1) login (do not rely on helper, since helper returned 400 earlier)
    const loginRes = await loginOrDie(agent, 'admin', 'admin123');

    // 2) Prefer cookie set during login
    let cookieStr = cookieHeaderToString(loginRes?.headers?.['set-cookie']);

    // 3) Fallback: some apps only set cookie on first authenticated request
    if (!cookieStr) {
      const probe1 = await agent.get('/api/me');
      cookieStr = cookieHeaderToString(probe1?.headers?.['set-cookie']);
    }
    if (!cookieStr) {
      const probe2 = await agent.get('/api/courses');
      cookieStr = cookieHeaderToString(probe2?.headers?.['set-cookie']);
    }

    if (!cookieStr) {
      throw new Error(
        'No Set-Cookie header observed after successful login + probes.\n' +
          'This may mean the app uses token-based auth (no session cookie),\n' +
          'or cookies are not being set in this test environment.\n' +
          `loginRes=${JSON.stringify(short(loginRes), null, 2)}\n`
      );
    }

    // Real checks (keep as "real fail" if missing)
    expect(cookieStr).toMatch(/httponly/i);
    expect(cookieStr).toMatch(/samesite=/i);

    // Optional stronger checks:
    // expect(cookieStr).toMatch(/samesite=(lax|strict|none)/i);
    // if (/samesite=none/i.test(cookieStr)) expect(cookieStr).toMatch(/secure/i);
  });
});



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