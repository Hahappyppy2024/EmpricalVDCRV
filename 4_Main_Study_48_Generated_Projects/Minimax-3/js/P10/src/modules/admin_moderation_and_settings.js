import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, forbidden, notFound, unauthorized
} from '../errors.js';
import {
  requireString, optionalString, optionalEnum, requireEnum
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';

function nowIso() { return new Date().toISOString(); }

function adminOnly(req) {
  if (!req.user) throw unauthorized();
  if (req.user.role !== 'admin') throw forbidden('Admin role required');
}

export function getSettings(req, res) {
  adminOnly(req);
  const rows = getDb().prepare(`
    SELECT key, value, updated_by, updated_at FROM admin_settings ORDER BY key ASC
  `).all();
  res.json({ ok: true, data: { items: rows } });
}

export function upsertSetting(req, res) {
  adminOnly(req);
  const key = requireString(req.body?.key, 'key', { min: 1, max: 64 });
  const value = requireString(req.body?.value ?? '', 'value', { min: 0, max: 4000 });
  getDb().prepare(`
    INSERT INTO admin_settings (key, value, updated_by, updated_at)
    VALUES (?, ?, ?, ?)
    ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by,
      updated_at = excluded.updated_at
  `).run(key, value, req.user.id, nowIso());
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'admin_setting.upsert', targetKind: 'admin_setting', targetId: key,
    outcome: 'success' });
  const row = getDb().prepare(`SELECT * FROM admin_settings WHERE key = ?`).get(key);
  res.json({ ok: true, data: row });
}

export function listBlockedTerms(req, res) {
  adminOnly(req);
  const rows = getDb().prepare(`
    SELECT id, term, severity, created_by, created_at FROM blocked_terms ORDER BY created_at DESC
  `).all();
  res.json({ ok: true, data: { items: rows } });
}

export function addBlockedTerm(req, res) {
  adminOnly(req);
  const term = requireString(req.body?.term, 'term', { min: 1, max: 200 });
  const severity = requireEnum(req.body?.severity || 'warn', 'severity', ['warn', 'block']);
  const id = 'blk_' + crypto.randomBytes(8).toString('hex');
  try {
    getDb().prepare(`INSERT INTO blocked_terms (id, term, severity, created_by, created_at)
      VALUES (?, ?, ?, ?, ?)`).run(id, term.toLowerCase(), severity, req.user.id, nowIso());
  } catch (err) {
    if (String(err.message).includes('UNIQUE')) throw badRequest('Term already exists');
    throw err;
  }
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'blocked_term.create', targetKind: 'blocked_term', targetId: id, outcome: 'success' });
  const row = getDb().prepare(`SELECT * FROM blocked_terms WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: row });
}

export function removeBlockedTerm(req, res) {
  adminOnly(req);
  const id = req.params.id;
  const db = getDb();
  const row = db.prepare(`SELECT * FROM blocked_terms WHERE id = ?`).get(id);
  if (!row) throw notFound('Blocked term not found');
  db.prepare(`DELETE FROM blocked_terms WHERE id = ?`).run(id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'blocked_term.delete', targetKind: 'blocked_term', targetId: id, outcome: 'success' });
  res.json({ ok: true, data: { id, deleted: true } });
}

export function listModeration(req, res) {
  adminOnly(req);
  const rows = getDb().prepare(`
    SELECT m.id, m.user_id, m.decision, m.reason, m.actor_id, m.created_at,
           u.email AS user_email, u.display_name AS user_name
    FROM user_moderation m
    LEFT JOIN users u ON u.id = m.user_id
    ORDER BY m.created_at DESC LIMIT 200
  `).all();
  res.json({ ok: true, data: { items: rows } });
}

export function moderateUser(req, res) {
  adminOnly(req);
  const db = getDb();
  const targetId = req.body?.user_id ? requireString(req.body.user_id, 'user_id',
    { min: 1, max: 64 }) : requireString(req.params.id, 'user_id', { min: 1, max: 64 });
  const decision = requireEnum(req.body?.decision, 'decision',
    ['suspend', 'reinstate', 'disable', 'reinstate_disabled']);
  const reason = requireString(req.body?.reason, 'reason', { min: 1, max: 500 });
  const target = db.prepare(`SELECT * FROM users WHERE id = ?`).get(targetId);
  if (!target) throw notFound('User not found');
  const id = 'mod_' + crypto.randomBytes(8).toString('hex');
  db.transaction(() => {
    if (decision === 'suspend') {
      db.prepare(`UPDATE users SET status = 'suspended', updated_at = ? WHERE id = ?`)
        .run(nowIso(), targetId);
    } else if (decision === 'disable') {
      db.prepare(`UPDATE users SET status = 'disabled', updated_at = ? WHERE id = ?`)
        .run(nowIso(), targetId);
    } else if (decision === 'reinstate' || decision === 'reinstate_disabled') {
      db.prepare(`UPDATE users SET status = 'active', updated_at = ? WHERE id = ?`)
        .run(nowIso(), targetId);
    }
    db.prepare(`INSERT INTO user_moderation (id, user_id, decision, reason, actor_id, created_at)
      VALUES (?, ?, ?, ?, ?, ?)`).run(id, targetId, decision, reason, req.user.id, nowIso());
  })();
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'user_moderation.record', targetKind: 'user', targetId: targetId,
    outcome: 'success', details: { decision, reason } });
  const refreshed = db.prepare(`SELECT id, email, display_name, role, status FROM users WHERE id = ?`)
    .get(targetId);
  res.json({ ok: true, data: { moderation_id: id, user: refreshed } });
}

export function listUsers(req, res) {
  adminOnly(req);
  const rows = getDb().prepare(`
    SELECT id, email, display_name, role, status, created_at FROM users ORDER BY created_at ASC
  `).all();
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function postAdminSettings(req, res) { return upsertSetting(req, res); }
export function patchAdminSettings(req, res) {
  adminOnly(req);
  const id = req.params.id;
  if (id === 'settings') return upsertSetting(req, res);
  if (id === 'blocked_terms') return addBlockedTerm(req, res);
  if (id === 'moderation') return moderateUser(req, res);
  throw badRequest('Unknown admin endpoint');
}
export function getAdminSettings(req, res) {
  // GET /api/ai/admin_moderation_and_settings — returns settings + blocked terms.
  adminOnly(req);
  const settings = getDb().prepare(`SELECT key, value FROM admin_settings ORDER BY key ASC`).all();
  const blocked = getDb().prepare(`SELECT id, term, severity FROM blocked_terms ORDER BY created_at DESC`).all();
  const moderation = getDb().prepare(`
    SELECT m.id, m.user_id, m.decision, m.reason, m.actor_id, m.created_at,
           u.email AS user_email
    FROM user_moderation m LEFT JOIN users u ON u.id = m.user_id
    ORDER BY m.created_at DESC LIMIT 50
  `).all();
  const users = getDb().prepare(`
    SELECT id, email, display_name, role, status FROM users ORDER BY created_at ASC
  `).all();
  res.json({
    ok: true,
    data: { settings, blocked_terms: blocked, moderation, users }
  });
}
