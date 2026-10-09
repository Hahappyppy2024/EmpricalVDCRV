import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';

function nowIso() { return new Date().toISOString(); }

export function recordEvent({
  actorId = null,
  actorRole = null,
  action,
  targetKind = null,
  targetId = null,
  outcome = 'success',
  details = null
}) {
  if (!action) throw new Error('audit.action required');
  const db = getDb();
  const id = 'aud_' + crypto.randomBytes(8).toString('hex');
  const detailsJson = details === null || details === undefined ? null : JSON.stringify(details);
  db.prepare(`INSERT INTO audit_events
    (id, actor_id, actor_role, action, target_kind, target_id, outcome, details, created_at)
    VALUES (?,?,?,?,?,?,?,?,?)`)
    .run(id, actorId, actorRole, action, targetKind, targetId, outcome, detailsJson, nowIso());
  return id;
}

export function listEventsForActor(actorId, limit = 100, offset = 0) {
  const db = getDb();
  return db.prepare(`
    SELECT id, actor_id, actor_role, action, target_kind, target_id, outcome, details, created_at
    FROM audit_events
    WHERE actor_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
  `).all(actorId, limit, offset);
}

export function listAllEvents({ action = null, targetKind = null, outcome = null, limit = 100, offset = 0 } = {}) {
  const db = getDb();
  const where = [];
  const params = [];
  if (action) { where.push('action = ?'); params.push(action); }
  if (targetKind) { where.push('target_kind = ?'); params.push(targetKind); }
  if (outcome) { where.push('outcome = ?'); params.push(outcome); }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  return db.prepare(`
    SELECT id, actor_id, actor_role, action, target_kind, target_id, outcome, details, created_at
    FROM audit_events
    ${whereSql}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
  `).all(...params, limit, offset);
}
