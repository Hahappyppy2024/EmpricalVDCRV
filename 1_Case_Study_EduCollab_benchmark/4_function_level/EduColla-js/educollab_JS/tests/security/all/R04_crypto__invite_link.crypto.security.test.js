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
  test('tokens are non-trivial and unique across multiple generations', async () => {
    const helperOut = makeAppWithFreshDb('sec_invite_tokens.db');
    const { agent, dbPath } = helperOut;
    console.log('DEBUG(R04) dbPath =', dbPath);

    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    const course_id = getCourseId(c);
    expect(course_id).toBeTruthy();

    const tokens = new Set();
    for (let i = 0; i < 20; i++) {
      const { token } = await createInvite(agent, course_id);
      expect(token.length).toBeGreaterThanOrEqual(20);
      expect(token).toMatch(/^[A-Za-z0-9\-_]+$/);
      tokens.add(token);
    }
    expect(tokens.size).toBe(20);
  });

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

  test.skip('expired token must be rejected (expiry enforced)', async () => {
    const helperOut = makeAppWithFreshDb('sec_invite_expiry.db');
    const { app, agent, dbPath } = helperOut;
    console.log('DEBUG(R04) dbPath =', dbPath);

    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C3', description: 'D3' });
    const course_id = getCourseId(c);
    expect(course_id).toBeTruthy();

    const inv = await createInvite(agent, course_id);

    // ✅ 用后端 DB 强制过期（同一个连接）
    assertInviteTokensTableExistsBackend();

    const db = getDb();
    db.prepare(`
      UPDATE invite_tokens
      SET expires_at = datetime('now','-10 minutes')
      WHERE invite_id = (SELECT MAX(invite_id) FROM invite_tokens)
    `).run();

    const bob = supertest.agent(app);
    await apiRegister(bob, { username: 'bob', password: 'password', display_name: 'Bob' });
    await apiLogin(bob, { username: 'bob', password: 'password' });

    const join = await bob
      .post('/api/join')
      .set('Content-Type', 'application/json')
      .send({ token: inv.token });

    expect([400, 403]).toContain(join.status);
  });
});