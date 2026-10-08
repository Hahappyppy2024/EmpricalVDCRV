import crypto from 'node:crypto';
import { getDb } from '../db/database.js';
import { config } from '../config.js';

export function nowSql() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function expiresSql(ttlMinutes = config.sessionTtlMinutes) {
  return new Date(Date.now() + ttlMinutes * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
}

export function createSession(userId) {
  const db = getDb();
  const token = crypto.randomBytes(32).toString('hex');
  db.prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)')
    .run(token, userId, expiresSql());
  return token;
}

export function getSessionByToken(token) {
  if (!token) return null;
  const db = getDb();
  return db.prepare(`
    SELECT s.id AS session_id, s.token, s.expires_at, s.revoked_at,
           u.id, u.email, u.name, u.role, u.phone, u.created_at AS member_since
    FROM sessions s
    JOIN users u ON u.id = s.user_id
    WHERE s.token = ?
  `).get(token);
}

export function isSessionActive(session) {
  if (!session) return false;
  if (session.revoked_at) return false;
  return new Date(session.expires_at + 'Z') > new Date();
}

export function revokeSessionByToken(token) {
  const db = getDb();
  db.prepare('UPDATE sessions SET revoked_at = ? WHERE token = ?').run(nowSql(), token);
}

export function revokeSessionById(userId, sessionId) {
  const db = getDb();
  const info = db.prepare('UPDATE sessions SET revoked_at = ? WHERE id = ? AND user_id = ?')
    .run(nowSql(), sessionId, userId);
  return info.changes > 0;
}

export function listSessionsForUser(userId) {
  const db = getDb();
  return db.prepare(`
    SELECT id, token, created_at, expires_at, revoked_at
    FROM sessions
    WHERE user_id = ?
    ORDER BY created_at DESC
  `).all(userId);
}
