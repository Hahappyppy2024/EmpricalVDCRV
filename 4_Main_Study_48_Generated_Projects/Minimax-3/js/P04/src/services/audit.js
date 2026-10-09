import { getDb } from '../db/connection.js';

export function record({ actor, action, targetType, targetId = null, detail = '' }) {
  const db = getDb();
  db.prepare(`INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, detail)
              VALUES (?, ?, ?, ?, ?, ?)`).run(actor ? actor.id : null, actor ? actor.role : 'system', action, targetType, targetId, detail);
}

export function recent(limit = 50) {
  return getDb().prepare(`SELECT a.*, COALESCE(u.display_name, 'system') AS actor_name
                          FROM audit_events a
                          LEFT JOIN users u ON u.id = a.actor_id
                          ORDER BY a.id DESC LIMIT ?`).all(limit);
}