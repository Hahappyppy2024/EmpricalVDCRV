export function createUsageService(db) {
  const insert = db.prepare(
    `INSERT INTO usage_logs (user_id, action, module, tokens_used, detail, created_at)
     VALUES (?, ?, ?, ?, ?, datetime('now'))`
  );
  return {
    record(userId, action, module, { tokensUsed = 0, detail = null } = {}) {
      insert.run(userId, action, module, Number(tokensUsed) || 0, detail ? JSON.stringify(detail) : null);
      return true;
    },
    list({ userId = null, module = null, action = null, from = null, to = null, limit = 50, offset = 0, actorOnly = false } = {}) {
      const clauses = [];
      const params = [];
      if (actorOnly && userId !== null) {
        clauses.push('ul.user_id = ?');
        params.push(userId);
      } else if (userId !== null) {
        clauses.push('ul.user_id = ?');
        params.push(userId);
      }
      if (module) {
        clauses.push('ul.module = ?');
        params.push(module);
      }
      if (action) {
        clauses.push('ul.action = ?');
        params.push(action);
      }
      if (from) {
        clauses.push('ul.created_at >= ?');
        params.push(from);
      }
      if (to) {
        clauses.push('ul.created_at <= ?');
        params.push(to);
      }
      const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
      const bounded = Math.min(Math.max(Number(limit) || 50, 1), 200);
      const off = Math.max(Number(offset) || 0, 0);
      const rows = db
        .prepare(
          `SELECT ul.id, ul.user_id, u.username, ul.action, ul.module, ul.tokens_used, ul.detail, ul.created_at
           FROM usage_logs ul LEFT JOIN users u ON u.id = ul.user_id
           ${where} ORDER BY ul.id DESC LIMIT ? OFFSET ?`
        )
        .all(...params, bounded, off);
      return rows.map((r) => ({ ...r, detail: safeParse(r.detail) }));
    },
    summarize(userId) {
      const totals = db
        .prepare(
          `SELECT module, COUNT(*) AS calls, COALESCE(SUM(tokens_used), 0) AS tokens
           FROM usage_logs WHERE user_id = ? GROUP BY module ORDER BY module`
        )
        .all(userId);
      return totals;
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
