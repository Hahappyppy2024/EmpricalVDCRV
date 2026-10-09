import { getDb } from '../db/connection.js';
import { newToken } from './crypto.js';
import { env } from '../config.js';

const cfg = env();

export function createSession(userId) {
  const db = getDb();
  const expires = new Date(Date.now() + cfg.SESSION_TTL_HOURS * 3600 * 1000).toISOString();
  const token = newToken();
  db.prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)').run(token, userId, expires);
  return { token, expires };
}

export function findSession(token) {
  if (!token) return null;
  const db = getDb();
  const row = db.prepare(`
    SELECT s.id AS token, s.expires_at, u.id, u.email, u.display_name, u.role
    FROM sessions s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ? AND s.expires_at > datetime('now')
  `).get(token);
  if (!row) return null;
  return {
    token: row.token,
    expires_at: row.expires_at,
    user: { id: row.id, email: row.email, display_name: row.display_name, role: row.role },
  };
}

export function destroySession(token) {
  if (!token) return;
  getDb().prepare('DELETE FROM sessions WHERE id = ?').run(token);
}

export function purgeExpired() {
  getDb().prepare("DELETE FROM sessions WHERE expires_at <= datetime('now')").run();
}