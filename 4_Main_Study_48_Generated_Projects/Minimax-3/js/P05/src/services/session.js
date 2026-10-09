import { getDb } from '../db/database.js';
import { nanoid } from 'nanoid';

const SESSION_COOKIE = 'cms_sid';
const SESSION_LIFETIME_MS = 1000 * 60 * 60 * 8; // 8 hours

export const COOKIE_NAME = SESSION_COOKIE;

export function createSession(userId, req) {
  const db = getDb();
  const id = nanoid(32);
  const expires = new Date(Date.now() + SESSION_LIFETIME_MS).toISOString();
  db.prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)').run(id, userId, expires);
  recordAccess(userId, 'login', req);
  return { id, expiresAt: expires };
}

export function destroySession(sid) {
  const db = getDb();
  if (!sid) return;
  const row = db.prepare('SELECT user_id FROM sessions WHERE id = ?').get(sid);
  if (row) {
    db.prepare('DELETE FROM sessions WHERE id = ?').run(sid);
  }
}

export function getSession(sid) {
  if (!sid) return null;
  const db = getDb();
  const session = db.prepare('SELECT * FROM sessions WHERE id = ?').get(sid);
  if (!session) return null;
  if (new Date(session.expires_at).getTime() < Date.now()) {
    db.prepare('DELETE FROM sessions WHERE id = ?').run(sid);
    return null;
  }
  const row = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(session.user_id);
  if (!row) return null;
  if (row.status !== 'active') return null;
  const user = {
    id: row.id,
    username: row.username,
    email: row.email,
    displayName: row.display_name,
    display_name: row.display_name,
    role: row.role_name,
    role_name: row.role_name,
    roleId: row.role_id,
    status: row.status,
    createdAt: row.created_at
  };
  return { session, user };
}

export function recordAccess(userId, event, req) {
  const db = getDb();
  const ip = req?.ip || req?.headers?.['x-forwarded-for'] || 'unknown';
  db.prepare('INSERT INTO account_access (user_id, event, ip_address) VALUES (?, ?, ?)').run(userId, event, ip);
}

export function purgeExpiredSessions() {
  const db = getDb();
  db.prepare("DELETE FROM sessions WHERE expires_at < datetime('now')").run();
}
