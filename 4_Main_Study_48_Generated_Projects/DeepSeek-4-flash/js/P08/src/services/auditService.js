export function writeAudit(db, { actorId = null, action, entityType = null, entityId = null, details = null }) {
  return db
    .prepare(
      `INSERT INTO audit_events (actor_id, action, entity_type, entity_id, details)
       VALUES (?, ?, ?, ?, ?)`
    )
    .run(actorId, action, entityType, entityId, details ? JSON.stringify(details) : null);
}

export function writeActivity(db, { reportId = null, actorId = null, type, message }) {
  return db
    .prepare(
      `INSERT INTO activity_events (report_id, actor_id, type, message)
       VALUES (?, ?, ?, ?)`
    )
    .run(reportId, actorId, type, message);
}

export function listAudit(db, { limit = 100 } = {}) {
  return db
    .prepare(
      `SELECT a.id, a.action, a.entity_type, a.entity_id, a.details, a.created_at,
              u.username, u.full_name
       FROM audit_events a
       LEFT JOIN users u ON u.id = a.actor_id
       ORDER BY a.id DESC
       LIMIT ?`
    )
    .all(limit);
}
