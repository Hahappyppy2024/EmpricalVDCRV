import { getDb } from '../db/index.js';

export function audit(db, { actorId, action, entityType, entityId, details }) {
  db.prepare(`INSERT INTO audit_events (actor_id, action, entity_type, entity_id, details)
    VALUES (@actorId, @action, @entityType, @entityId, @details)`)
    .run({
      actorId: actorId ?? null,
      action: String(action || 'unknown').slice(0, 80),
      entityType: entityType ? String(entityType).slice(0, 80) : null,
      entityId: entityId ?? null,
      details: details ? String(details).slice(0, 2000) : null
    });
}

export function listAudit(db, { limit = 50, actorId } = {}) {
  const where = actorId ? 'WHERE a.actor_id = @actorId' : '';
  return db.prepare(`
    SELECT a.*, u.username AS actor_username
    FROM audit_events a
    LEFT JOIN users u ON u.id = a.actor_id
    ${where}
    ORDER BY a.id DESC
    LIMIT @limit
  `).all({ actorId: actorId ?? null, limit: Math.min(500, Number(limit) || 50) });
}

export function auditFor(req, action, entityType, entityId, details) {
  audit(getDb(), {
    actorId: req.user ? req.user.id : null,
    action,
    entityType,
    entityId,
    details
  });
}
