import { db } from '../db/index.js';

export function getUserByCredentials(username, passwordHash) {
  return db.prepare('SELECT * FROM users WHERE username = ? AND password_hash = ?').get(username, passwordHash);
}

export function findUserByUsername(username) {
  return db.prepare('SELECT * FROM users WHERE username = ?').get(username);
}

export function findUserById(id) {
  return db.prepare('SELECT * FROM users WHERE id = ?').get(id);
}

export function listUsers() {
  return db.prepare(`
    SELECT u.id, u.username, u.email, u.full_name, u.role, u.manager_id,
           u.department_id, d.name AS department_name, d.cost_center
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    ORDER BY u.role, u.username
  `).all();
}

export function recordAudit(actorId, entityType, entityId, action, payload) {
  db.prepare(
    'INSERT INTO audit_events (actor_id, entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?, ?)'
  ).run(actorId, entityType, entityId, action, payload ? JSON.stringify(payload) : null);
}

export function recordActivity(actorId, reportId, eventType, details) {
  db.prepare(
    'INSERT INTO activity_logs (actor_id, report_id, event_type, details) VALUES (?, ?, ?, ?)'
  ).run(actorId, reportId, eventType, details ? JSON.stringify(details) : null);
}
