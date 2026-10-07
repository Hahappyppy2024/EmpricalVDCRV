
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

4.0 VulType
VulType: R02

4.1 RelatedFiles
- MISSING: Route handler for POST /api/courses/:course_id/invites (invite creation)
- MISSING: Route handler for POST /api/join (invite consumption)
- MISSING: Database schema or model for invite_tokens

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic for creating invite tokens and storing them in the database

File: MISSING
- Needed: Logic for verifying invite tokens and marking them as used

4.3 RootCause
- The application stores invite tokens in plaintext in the database instead of storing a cryptographic hash (e.g., SHA-256), violating the secure storage requirement.
- The application fails to invalidate tokens after use, allowing the same token to be redeemed multiple times (lack of single-use enforcement).

4.4 ActionablePlan
- Target File: routes/invites.js (or controller)
  Target: POST /api/courses/:course_id/invites handler
  Change: Generate a random token, hash it (e.g., sha256), and store only the hash in the database. Return the raw token to the client.

- Target File: routes/join.js (or controller)
  Target: POST /api/join handler
  Change: Hash the received token and look it up in the database. If found and valid, complete the join action and delete the token or mark it as used to prevent reuse. Return 403 if the token is invalid or already used.

4.5 FileToActionMap
- routes/invites.js → Implement secure token hashing before storage.
- routes/join.js → Implement token lookup by hash and invalidation after use.

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