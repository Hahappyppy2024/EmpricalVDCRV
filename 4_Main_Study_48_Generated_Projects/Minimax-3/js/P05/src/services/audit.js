import { getDb } from '../db/database.js';

export function logApiError({ endpoint, method, code, errorClass, message, userId }) {
  const db = getDb();
  db.prepare(
    'INSERT INTO frontend_api_integration_and_errors (endpoint, method, response_code, error_class, message, observed_by) VALUES (?, ?, ?, ?, ?, ?)'
  ).run(endpoint, method, code, errorClass || null, message || null, userId || null);
}

export function logAudit({ actorId, action, resource, details }) {
  const db = getDb();
  db.prepare('INSERT INTO audit_events (actor_id, action, resource, details) VALUES (?, ?, ?, ?)').run(actorId || null, action, resource, details || null);
}
