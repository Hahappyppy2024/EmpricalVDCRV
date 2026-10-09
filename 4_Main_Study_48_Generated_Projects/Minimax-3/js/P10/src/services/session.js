import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { config } from '../config.js';

function nowIso() { return new Date().toISOString(); }
function futureIso(seconds) { return new Date(Date.now() + seconds * 1000).toISOString(); }

export function createSession(userId) {
  const db = getDb();
  const id = 'sess_' + crypto.randomBytes(18).toString('base64url');
  const createdAt = nowIso();
  const expiresAt = futureIso(config.sessionLifetimeSeconds);
  db.prepare(`INSERT INTO sessions (id, user_id, created_at, expires_at) VALUES (?, ?, ?, ?)`)
    .run(id, userId, createdAt, expiresAt);
  return { id, user_id: userId, created_at: createdAt, expires_at: expiresAt };
}

export function revokeSession(sessionId) {
  const db = getDb();
  db.prepare(`UPDATE sessions SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL`)
    .run(nowIso(), sessionId);
}

export function purgeExpiredSessions() {
  const db = getDb();
  return db.prepare(`DELETE FROM sessions WHERE expires_at < ? OR revoked_at IS NOT NULL`).run(nowIso()).changes;
}

export function loadSessionUser(sessionId) {
  if (!sessionId) return null;
  const db = getDb();
  const row = db.prepare(`
    SELECT s.id as sid, s.expires_at, s.revoked_at,
           u.id, u.email, u.display_name, u.role, u.status
    FROM sessions s JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
  `).get(sessionId);
  if (!row) return null;
  if (row.revoked_at) return null;
  if (new Date(row.expires_at).getTime() < Date.now()) return null;
  if (row.status !== 'active') return null;
  return {
    sid: row.sid,
    user: {
      id: row.id, email: row.email, display_name: row.display_name,
      role: row.role, status: row.status
    }
  };
}

export function setSessionCookie(res, sessionId) {
  const maxAgeMs = config.sessionLifetimeSeconds * 1000;
  const cookie = [
    `${config.sessionCookieName}=${sessionId}`,
    'Path=/',
    'HttpOnly',
    'SameSite=Lax',
    `Max-Age=${Math.floor(maxAgeMs / 1000)}`
  ].join('; ');
  res.setHeader('Set-Cookie', cookie);
}

export function clearSessionCookie(res) {
  const cookie = [
    `${config.sessionCookieName}=`,
    'Path=/',
    'HttpOnly',
    'SameSite=Lax',
    'Max-Age=0'
  ].join('; ');
  res.setHeader('Set-Cookie', cookie);
}

export function readSessionCookie(req) {
  const header = req.headers.cookie;
  if (!header) return null;
  const parts = header.split(';');
  for (const part of parts) {
    const idx = part.indexOf('=');
    if (idx < 0) continue;
    const name = part.slice(0, idx).trim();
    if (name === config.sessionCookieName) return part.slice(idx + 1).trim();
  }
  return null;
}
