const crypto = require('crypto');
const { getDb } = require('../db');

function base64url(buf) {
  return buf
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');
}

// 
function generateToken() {
  if (process.env.INVITE_TEST_MODE === 'fixed') {
    return 'fixedtoken_fixedtoken_fixedtoken';
  }
  // 32 bytes -> ~43 chars base64url-ish
  return base64url(crypto.randomBytes(32));
}

function sha256Hex(s) {
  return crypto.createHash('sha256').update(s).digest('hex');
}

async function createInvite({ course_id, role_in_course, ttl_minutes }) {
  const token = generateToken();
  const token_hash = sha256Hex(token);

  const ttl = Number(ttl_minutes || 60);
  const db = getDb();

  db.prepare(
    `INSERT INTO invite_tokens(course_id, role_in_course, token_hash, expires_at, used_at, created_at, updated_at)
     VALUES(?, ?, ?, datetime('now', ?), NULL, datetime('now'), datetime('now'))`
  ).run(course_id, role_in_course, token_hash, `+${ttl} minutes`);

  return { token };
}

async function consumeInviteToken({ token }) {
  const token_hash = sha256Hex(token);
  const db = getDb();

  const row = db
    .prepare(
      `SELECT invite_id, course_id, role_in_course, expires_at, used_at
       FROM invite_tokens
       WHERE token_hash = ?`
    )
    .get(token_hash);

  if (!row) return { ok: false, reason: 'invalid' };

  const expired = db
    .prepare(`SELECT datetime(?) < datetime('now') AS expired`)
    .get(row.expires_at).expired;

  if (expired) return { ok: false, reason: 'expired' };
  if (row.used_at) return { ok: false, reason: 'used' };

  db.prepare(
    `UPDATE invite_tokens SET used_at = datetime('now'), updated_at = datetime('now') WHERE invite_id = ?`
  ).run(row.invite_id);

  return { ok: true, course_id: row.course_id, role_in_course: row.role_in_course };
}

module.exports = {
  createInvite,
  consumeInviteToken,
  sha256Hex,
  generateToken,
};