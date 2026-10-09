import crypto from 'node:crypto';
import { db } from '../db/index.js';

const SESSION_TTL_HOURS = 12;

export const SESSION_COOKIE_NAME = process.env.SESSION_COOKIE_NAME || 'exp_session';

export function createSession(userId) {
  const id = crypto.randomBytes(32).toString('hex');
  const expiresAt = new Date(Date.now() + SESSION_TTL_HOURS * 3600 * 1000).toISOString();
  db.prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)').run(id, userId, expiresAt);
  return { id, expiresAt };
}

export function destroySession(sessionId) {
  if (!sessionId) return false;
  const res = db.prepare('DELETE FROM sessions WHERE id = ?').run(sessionId);
  return res.changes > 0;
}

export function getSession(sessionId) {
  if (!sessionId) return null;
  const row = db.prepare(`
    SELECT s.id AS session_id, s.expires_at, u.id AS user_id, u.username, u.email, u.full_name,
           u.role, u.department_id, u.manager_id, d.name AS department_name, d.cost_center
    FROM sessions s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE s.id = ? AND datetime(s.expires_at) > datetime('now')
  `).get(sessionId);
  if (!row) return null;
  return {
    id: row.session_id,
    expiresAt: row.expires_at,
    user: {
      id: row.user_id,
      username: row.username,
      email: row.email,
      fullName: row.full_name,
      role: row.role,
      departmentId: row.department_id,
      departmentName: row.department_name,
      costCenter: row.cost_center,
      managerId: row.manager_id
    }
  };
}

export function purgeExpiredSessions() {
  const res = db.prepare("DELETE FROM sessions WHERE datetime(expires_at) <= datetime('now')").run();
  return res.changes;
}

export function buildSessionMiddleware() {
  return (req, _res, next) => {
    const sid = req.cookies?.[SESSION_COOKIE_NAME];
    req.session = getSession(sid);
    next();
  };
}

export const sessionUtils = { createSession, destroySession, getSession };
