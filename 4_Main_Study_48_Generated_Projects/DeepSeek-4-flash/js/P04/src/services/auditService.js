import { getDb } from '../db/database.js';

export function recordAudit({ actorId, action, targetType, targetId, details }) {
  const db = getDb();
  db.prepare(`
    INSERT INTO audit_events (actor_id, action, target_type, target_id, details)
    VALUES (?, ?, ?, ?, ?)
  `).run(actorId || null, action, targetType, targetId == null ? null : String(targetId), details ? JSON.stringify(details) : null);
}

export function listAudit({ actorId, limit = 50 } = {}) {
  const db = getDb();
  if (actorId) {
    return db.prepare(`
      SELECT * FROM audit_events WHERE actor_id = ? ORDER BY created_at DESC, id DESC LIMIT ?
    `).all(actorId, limit);
  }
  return db.prepare(`
    SELECT * FROM audit_events ORDER BY created_at DESC, id DESC LIMIT ?
  `).all(limit);
}
