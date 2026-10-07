

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


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