export function createAuditService(db) {
  const insert = db.prepare(
    `INSERT INTO audit_events (user_id, action, module, detail, created_at)
     VALUES (?, ?, ?, ?, datetime('now'))`
  );
  return {
    record(userId, action, module, detail) {
      insert.run(userId ?? null, action, module, detail ? JSON.stringify(detail) : null);
      return true;
    },
    list({ userId = null, module = null, action = null, limit = 50, offset = 0, actorOnly = false } = {}) {
      const clauses = [];
      const params = [];
      if (actorOnly && userId !== null) {
        clauses.push('ae.user_id = ?');
        params.push(userId);
      } else if (userId !== null) {
        clauses.push('(ae.user_id = ? OR ae.user_id IS NULL)');
        params.push(userId);
      }
      if (module) {
        clauses.push('ae.module = ?');
        params.push(module);
      }
      if (action) {
        clauses.push('ae.action = ?');
        params.push(action);
      }
      const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
      const bounded = Math.min(Math.max(Number(limit) || 50, 1), 200);
      const off = Math.max(Number(offset) || 0, 0);
      const rows = db
        .prepare(
          `SELECT ae.id, ae.user_id, u.username, ae.action, ae.module, ae.detail, ae.created_at
           FROM audit_events ae LEFT JOIN users u ON u.id = ae.user_id
           ${where} ORDER BY ae.id DESC LIMIT ? OFFSET ?`
        )
        .all(...params, bounded, off);
      return rows.map((r) => ({ ...r, detail: safeParse(r.detail) }));
    },
  };
}

function safeParse(value) {
  if (value === null || value === undefined) return null;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
}
