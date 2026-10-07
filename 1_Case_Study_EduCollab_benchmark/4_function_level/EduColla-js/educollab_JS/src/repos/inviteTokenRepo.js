const crypto = require('crypto');
const { db, nowIso } = require('../db');

/**
 * Generate a base64url token (unhashed) and store only its SHA-256 hash.
 * Returns: { token, invite }
 */
function createInvite({ course_id, role_in_course, created_by, ttl_minutes = 60 * 24 }) {
  const token = crypto.randomBytes(32).toString('base64url'); // Node 16+
  const token_hash = sha256(token);
  const created_at = nowIso();
  const expires_at = new Date(Date.now() + ttl_minutes * 60 * 1000).toISOString();

  const info = db.prepare(`
    INSERT INTO invite_tokens (course_id, role_in_course, token_hash, created_by, created_at, expires_at)
    VALUES (?,?,?,?,?,?)
  `).run(course_id, role_in_course, token_hash, created_by, created_at, expires_at);

  return { token, invite: getById(info.lastInsertRowid) };
}

function sha256(s) {
  return crypto.createHash('sha256').update(s, 'utf8').digest('hex');
}

function getById(invite_id) {
  return db.prepare('SELECT * FROM invite_tokens WHERE invite_id=?').get(invite_id);
}


function getValidByToken(token) {

  const token_hash = sha256(token);

  const row = db.prepare('SELECT * FROM invite_tokens WHERE token_hash=?').get(token_hash);

  if (!row) return null;
  // must be un-used and not expired
  if (row.used_at) return null;
  const now = new Date();
  const exp = new Date(row.expires_at);
  if (isNaN(exp.getTime()) || exp < now) return null;
  return row;
}

function markUsed(invite_id, used_by) {
  db.prepare('UPDATE invite_tokens SET used_at=?, used_by=? WHERE invite_id=?').run(nowIso(), used_by, invite_id);
  return getById(invite_id);
}

module.exports = {
  createInvite,
  getValidByToken,
  markUsed,
  sha256,
};
