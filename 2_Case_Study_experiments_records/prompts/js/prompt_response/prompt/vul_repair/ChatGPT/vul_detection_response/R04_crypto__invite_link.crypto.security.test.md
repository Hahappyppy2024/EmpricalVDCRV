
You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)

Secure invite-link joining: course invite tokens must be stored as a hash (not plaintext) and enforced as single-use, so the first /api/join succeeds and any reuse is rejected.

### 3) Failed Security Test
const url = require('url');
const supertest = require('supertest');
const path = require('path');

const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');

// 
const { getDb } = require(path.join(__dirname, '..', '..', 'src', 'db'));

console.log('SEC TEST VERSION: R04 invite link crypto v7 (use backend getDb only)');

function extractToken(invite_link) {
  const u = new url.URL(invite_link, 'http://localhost');
  return u.searchParams.get('token');
}

function getCourseId(res) {
  const b = res?.body || {};
  return b?.course?.course_id ?? b?.course_id ?? b?.course?.id ?? b?.id ?? null;
}

async function createInvite(agent, course_id) {
  const inv = await agent
    .post(`/api/courses/${course_id}/invites`)
    .set('Content-Type', 'application/json')
    .send({ role_in_course: 'student', ttl_minutes: 60 });

  if (![200, 201].includes(inv.status)) {
    throw new Error(`Invite create failed: status=${inv.status} body=${JSON.stringify(inv.body)}`);
  }

  const inviteLink = inv.body?.invite_link || inv.body?.inviteLink;
  if (!inviteLink) throw new Error(`Invite response missing invite_link: ${JSON.stringify(inv.body)}`);

  const token = extractToken(inviteLink);
  if (!token) throw new Error(`Invite link missing token param. invite_link=${inviteLink}`);

  return { token, inviteLink };
}

function assertInviteTokensTableExistsBackend() {
  const db = getDb();
  const chk = db
    .prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='invite_tokens'")
    .get();
  expect(!!chk).toBe(true);
}

describe('Security: Invite link (Cryptographic Failures)', () => {
  test('token is single-use and not stored in plaintext (hash-only)', async () => {
    const helperOut = makeAppWithFreshDb('sec_invite_single_use.db');
    const { app, agent, dbPath } = helperOut;
    console.log('DEBUG(R04) dbPath =', dbPath);

    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C2', description: 'D2' });
    const course_id = getCourseId(c);
    expect(course_id).toBeTruthy();

    const inv = await createInvite(agent, course_id);

    // ✅ 用后端 DB 检查表和数据（同一个连接）
    assertInviteTokensTableExistsBackend();

    const db = getDb();
    const rows = db.prepare('SELECT invite_id, token_hash FROM invite_tokens').all();
    expect(rows.length).toBeGreaterThanOrEqual(1);

    const tokenHash = rows[rows.length - 1].token_hash;
    expect(tokenHash).toMatch(/^[a-f0-9]{64}$/i);
    expect(JSON.stringify(rows)).not.toContain(inv.token);

    // Join checks
    const bob = supertest.agent(app);
    await apiRegister(bob, { username: 'bob', password: 'password', display_name: 'Bob' });
    await apiLogin(bob, { username: 'bob', password: 'password' });

    const join1 = await bob
      .post('/api/join')
      .set('Content-Type', 'application/json')
      .send({ token: inv.token });
    expect([200, 201]).toContain(join1.status);

    const charlie = supertest.agent(app);
    await apiRegister(charlie, { username: 'charlie', password: 'password', display_name: 'Charlie' });
    await apiLogin(charlie, { username: 'charlie', password: 'password' });

    const join2 = await charlie
      .post('/api/join')
      .set('Content-Type', 'application/json')
      .send({ token: inv.token });
    expect([400, 403]).toContain(join2.status);
  });

});



### 4.0 VulType

VulType: R04

---

### 4.1 RelatedFiles

RelatedFiles:

* src/db.js
* MISSING: API route/controller for `POST /api/courses/:course_id/invites` (invite creation)
* MISSING: API route/controller for `POST /api/join` (invite redemption / join)
* MISSING: Data layer (model/repo) that writes/reads `invite_tokens` table (token hashing + single-use enforcement)
* MISSING: DB schema / migration that defines `invite_tokens(invite_id, token_hash, …, used_at/used_by/expires_at, …)` (if schema is not created in code)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: src/db.js

* Route/Middleware/Function: `getDb`
* Relevant Code:

  ```js
  const { getDb } = require(path.join(__dirname, '..', '..', 'src', 'db'));
  ```

  (Test imports `getDb()` from `src/db.js` and then directly queries `invite_tokens` via that backend connection.)

File: MISSING

* Needed: route definition + handler for `POST /api/courses/:course_id/invites` showing how the token is generated and persisted (must store only a hash in `invite_tokens.token_hash`, not plaintext)

File: MISSING

* Needed: route definition + handler for `POST /api/join` showing how the provided plaintext token is validated (hash compare), how membership is granted, and how the token is marked as used/rejected on reuse

File: MISSING

* Needed: DB write/read code for `invite_tokens` (insertion of token_hash; selection by token_hash; “single-use” update such as setting `used_at` / deleting row)

File: MISSING

* Needed: schema creation for `invite_tokens` to confirm `token_hash` exists and whether there is a “used” marker to enforce single-use

---

### 4.3 RootCause

RootCause:

* Invite tokens are **not stored hash-only**: the invite creation path likely stores the raw token (or stores a non-64-hex value), causing `token_hash` to fail the `/^[a-f0-9]{64}$/` expectation and/or `JSON.stringify(rows)` to contain the plaintext token.
* `/api/join` does **not enforce single-use**: the join handler likely allows multiple redemptions of the same token (no “mark used” update / delete, or no check for `used_at` / remaining uses).
* The enforcement must happen at the **controller/repo level** for both endpoints:

  * On creation: generate random token, store only its hash
  * On join: hash incoming token and match against stored hash; then atomically consume it

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Invite creation route/controller** (MISSING)
  Target: `POST /api/courses/:course_id/invites`
  Change: Generate a cryptographically strong random token (returned in `invite_link`), compute `token_hash = sha256(token)` (hex, 64 chars), and store **only** `token_hash` in `invite_tokens`. Never store/return the plaintext token except inside the invite link response.

* Target File: **Invite redemption route/controller** (MISSING)
  Target: `POST /api/join`
  Change: Require authentication; hash the provided `token` with the same sha256; look up a valid, unexpired, unused invite row by `token_hash`. If not found, return **400/403**. If found, create membership and then **consume** the invite so reuse fails.

* Target File: **Invite token data access / DB transaction logic** (MISSING)
  Target: “consume invite” operation
  Change: Make redemption single-use with an atomic operation (single transaction): verify unused + not expired, then either (a) delete the row, or (b) set `used_at` and/or `used_by_user_id` and ensure subsequent lookups require `used_at IS NULL`. This prevents race/reuse.

* Target File: **DB schema / initialization** (MISSING)
  Target: `invite_tokens` table definition
  Change: Ensure `token_hash` column exists and is used; add `used_at` (nullable) and `expires_at` (or equivalent) if missing so you can enforce single-use + TTL as required by `ttl_minutes`.

---

### 4.5 FileToActionMap

FileToActionMap:

* src/db.js → ensure `getDb()` returns the backend connection used by routes so `invite_tokens` writes are visible to the test queries
* MISSING: invites route/controller → store `sha256(token)` in `invite_tokens.token_hash` and never persist plaintext token
* MISSING: join route/controller → validate by hashing incoming token, reject invalid/used tokens, and consume token after first successful join
* MISSING: invite token repo/DB ops → implement atomic “find valid invite + consume” to enforce single-use
* MISSING: schema/init for `invite_tokens` → ensure `token_hash` (64-hex) + “used” marker + TTL fields exist and are enforced


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