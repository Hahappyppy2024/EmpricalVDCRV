import { getDb } from '../db/connection.js';
import { forbidden, notFound, unauthorized } from '../errors.js';
import { optionalString, optionalEnum, optionalNumber } from '../services/validation.js';
import { listEventsForActor, listAllEvents } from '../services/audit.js';

export function getUsageAndAudit(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const isAdmin = req.user.role === 'admin';
  const action = optionalString(req.query.action, 'action', { min: 1, max: 80 }) || null;
  const targetKind = optionalString(req.query.target_kind, 'target_kind', { min: 1, max: 40 }) || null;
  const outcome = optionalEnum(req.query.outcome, 'outcome', ['success', 'failure', 'info']) || null;
  const limit = optionalNumber(req.query.limit, 'limit', { min: 1, max: 500, integer: true }) || 50;
  const offset = optionalNumber(req.query.offset, 'offset', { min: 0, max: 100000, integer: true }) || 0;

  // Filter parameters are applied; if a non-admin user requests filters that
  // are not their own we silently bound the result to their own actor.
  const filterActor = req.query.actor_id ? optionalString(req.query.actor_id, 'actor_id', { min: 1, max: 64 }) : null;
  if (!isAdmin && filterActor && filterActor !== req.user.id) {
    throw forbidden('Cannot inspect another user\'s audit log');
  }

  const audit = isAdmin
    ? listAllEvents({ action, targetKind, outcome, limit, offset })
    : listEventsForActor(req.user.id, limit, offset);

  const usageWhere = ['user_id = ?'];
  const usageParams = [req.user.id];
  if (req.query.model_id) {
    usageWhere.push('model_id = ?');
    usageParams.push(String(req.query.model_id));
  }
  const usageRows = db.prepare(`
    SELECT id, conversation_id, model_id, prompt_tokens, completion_tokens, created_at
    FROM usage_records WHERE ${usageWhere.join(' AND ')}
    ORDER BY created_at DESC LIMIT ?
  `).all(...usageParams, limit);

  // Aggregates — non-admins only see their own totals.
  const aggregate = db.prepare(`
    SELECT COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
           COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
           COUNT(*) AS executions
    FROM usage_records WHERE user_id = ?
  `).get(req.user.id);

  res.json({
    ok: true,
    data: {
      audit,
      usage: usageRows,
      aggregate,
      filters: {
        action, target_kind: targetKind, outcome,
        limit, offset
      }
    }
  });
}

export function getAuditEvent(req, res) {
  if (!req.user) throw unauthorized();
  const row = getDb().prepare(`SELECT * FROM audit_events WHERE id = ?`).get(req.params.id);
  if (!row) throw notFound('Audit event not found');
  if (row.actor_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  res.json({ ok: true, data: row });
}

export function getUsageAndAuditLogs(req, res) { return getUsageAndAudit(req, res); }
export function patchUsageAndAuditLogs(req, res) { return getAuditEvent(req, res); }
export function postUsageAndAuditLogs(req, res) { return getUsageAndAudit(req, res); }
