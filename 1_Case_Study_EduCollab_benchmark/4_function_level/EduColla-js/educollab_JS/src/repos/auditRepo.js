const { db, nowIso } = require('../db');

function create({ actor_user_id = null, actor_username = null, action, target_type = null, target_id = null, meta_json = null }) {
  const metaStr = meta_json ? JSON.stringify(meta_json) : null;
  const stmt = db.prepare(`
    INSERT INTO audit_log (actor_user_id, actor_username, action, target_type, target_id, meta_json, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `);
  const info = stmt.run(actor_user_id, actor_username, action, target_type, target_id, metaStr, nowIso());
  return { audit_id: info.lastInsertRowid };
}

function listRecent(limit = 200) {
  return db.prepare(`
    SELECT audit_id, actor_user_id, actor_username, action, target_type, target_id, meta_json, created_at
    FROM audit_log
    ORDER BY audit_id DESC
    LIMIT ?
  `).all(limit);
}

module.exports = { create, listRecent };
