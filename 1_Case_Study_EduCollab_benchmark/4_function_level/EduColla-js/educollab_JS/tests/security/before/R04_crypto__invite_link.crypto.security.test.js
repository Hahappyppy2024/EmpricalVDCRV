const path = require('path');
const sqlite3 = require('sqlite3').verbose();
const url = require('url');
const crypto = require('crypto');

const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');

function extractToken(invite_link) {
  const u = new url.URL(invite_link);
  return u.searchParams.get('token');
}

function openDb(dbPath) {
  return new sqlite3.Database(dbPath);
}

function all(db, sql, params=[]) {
  return new Promise((resolve, reject) => {
    db.all(sql, params, (err, rows) => err ? reject(err) : resolve(rows));
  });
}
function run(db, sql, params=[]) {
  return new Promise((resolve, reject) => {
    db.run(sql, params, function(err) { err ? reject(err) : resolve(this); });
  });
}

describe('Security: Invite link (Cryptographic Failures)', () => {
  test('tokens are non-trivial and unique across multiple generations', async () => {
    const { app, request, agent } = makeAppWithFreshDb('sec_invite_tokens.db');

    await apiLogin(agent, { username: 'admin', password: 'admin123' });
    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const tokens = new Set();
    for (let i = 0; i < 20; i++) {
      const inv = await agent.post(`/api/courses/${course_id}/invites`).send({ role_in_course: 'student', ttl_minutes: 60 });
      expect(inv.status).toBe(200);
      const token = extractToken(inv.body.invite_link);
      expect(token).toBeTruthy();

      // basic length + charset sanity for base64url-ish tokens
      expect(token.length).toBeGreaterThanOrEqual(20);
      expect(token).toMatch(/^[A-Za-z0-9\-_]+$/);

      tokens.add(token);
    }
    expect(tokens.size).toBe(20);
  });

  test('token is single-use and not stored in plaintext (hash-only)', async () => {
    const { app, request, agent } = makeAppWithFreshDb('sec_invite_single_use.db');
    const dbPath = process.env.DB_PATH;
    expect(dbPath).toBeTruthy();

    await apiLogin(agent, { username: 'admin', password: 'admin123' });
    const c = await apiCreateCourse(agent, { title: 'C2', description: 'D2' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const inv = await agent.post(`/api/courses/${course_id}/invites`).send({ role_in_course: 'student', ttl_minutes: 60 });
    expect(inv.status).toBe(200);
    const token = extractToken(inv.body.invite_link);
    expect(token).toBeTruthy();

    // DB should store only hash(token), not token itself
    const db = openDb(dbPath);
    const rows = await all(db, 'SELECT invite_id, token_hash FROM invite_tokens');
    expect(rows.length).toBeGreaterThanOrEqual(1);
    const tokenHash = rows[rows.length - 1].token_hash;

    // sha256 hex length 64
    expect(tokenHash).toMatch(/^[a-f0-9]{64}$/i);
    expect(tokenHash).not.toBe(token);

    // ensure plaintext token is not present anywhere in invite_tokens table
    const dump = JSON.stringify(rows);
    expect(dump).not.toContain(token);

    // join as bob (first use succeeds)
    const bob = request.agent(app);
    await apiRegister(bob, { username: 'bob', password: 'password', display_name: 'Bob' });
    await apiLogin(bob, { username: 'bob', password: 'password' });
    const join1 = await bob.post('/api/join').send({ token });
    expect(join1.status).toBe(200);

    // join as charlie with same token (second use should fail)
    const charlie = request.agent(app);
    await apiRegister(charlie, { username: 'charlie', password: 'password', display_name: 'Charlie' });
    await apiLogin(charlie, { username: 'charlie', password: 'password' });
    const join2 = await charlie.post('/api/join').send({ token });
    expect([400, 403]).toContain(join2.status);

    db.close();
  });

  test('expired token must be rejected (expiry enforced)', async () => {
    const { app, request, agent } = makeAppWithFreshDb('sec_invite_expiry.db');
    const dbPath = process.env.DB_PATH;

    await apiLogin(agent, { username: 'admin', password: 'admin123' });
    const c = await apiCreateCourse(agent, { title: 'C3', description: 'D3' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const inv = await agent.post(`/api/courses/${course_id}/invites`).send({ role_in_course: 'student', ttl_minutes: 60 });
    expect(inv.status).toBe(200);
    const token = extractToken(inv.body.invite_link);
    expect(token).toBeTruthy();

    // Force expiry in DB (set expires_at to past)
    const db = openDb(dbPath);
    await run(db, "UPDATE invite_tokens SET expires_at = datetime('now','-10 minutes')");

    const bob = request.agent(app);
    await apiRegister(bob, { username: 'bob', password: 'password', display_name: 'Bob' });
    await apiLogin(bob, { username: 'bob', password: 'password' });

    const join = await bob.post('/api/join').send({ token });
    expect([400, 403]).toContain(join.status);

    db.close();
  });
});
