import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { badRequest, conflict, forbidden, notFound, unauthorized } from '../errors.js';
import { requireString, optionalString, optionalEnum } from '../services/validation.js';
import { recordEvent } from '../services/audit.js';

function newId() { return 'tpl_' + crypto.randomBytes(8).toString('hex'); }
function nowIso() { return new Date().toISOString(); }

export function listTemplates(req, res) {
  if (!req.user) throw unauthorized();
  const scope = optionalEnum(req.query.scope, 'scope', ['user', 'shared']);
  const db = getDb();
  const where = [
    '(t.owner_id = ? OR t.scope = ?)'
  ];
  const params = [req.user.id, 'shared'];
  if (scope) { where.push('t.scope = ?'); params.push(scope); }
  const rows = db.prepare(`
    SELECT t.id, t.owner_id, t.scope, t.name, t.description, t.body, t.created_at, t.updated_at
    FROM prompt_templates t
    WHERE ${where.join(' AND ')}
    ORDER BY t.updated_at DESC
    LIMIT 200
  `).all(...params);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function createTemplate(req, res) {
  if (!req.user) throw unauthorized();
  const name = requireString(req.body?.name, 'name', { min: 1, max: 120 });
  const body = requireString(req.body?.body, 'body', { min: 1, max: 20000 });
  const description = optionalString(req.body?.description, 'description', { min: 0, max: 500 }) || null;
  let scope = optionalEnum(req.body?.scope, 'scope', ['user', 'shared']) || 'user';
  if (scope === 'shared' && req.user.role !== 'admin') throw forbidden('Only admins may publish shared templates');
  const db = getDb();
  const existing = db.prepare(`
    SELECT id FROM prompt_templates
    WHERE name = ? AND scope = ? AND (owner_id IS ? OR owner_id = ?)
  `).get(name, scope, scope === 'shared' ? null : req.user.id, scope === 'shared' ? '' : req.user.id);
  if (existing) throw conflict('Template with this name already exists');
  const id = newId();
  db.prepare(`INSERT INTO prompt_templates
    (id, owner_id, scope, name, description, body, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?)`).run(
      id, scope === 'shared' ? null : req.user.id, scope, name, description, body, nowIso(), nowIso()
    );
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'prompt_template.create', targetKind: 'prompt_template', targetId: id,
    outcome: 'success' });
  const stored = db.prepare(`SELECT * FROM prompt_templates WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function updateTemplate(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const tpl = db.prepare(`SELECT * FROM prompt_templates WHERE id = ?`).get(req.params.id);
  if (!tpl) throw notFound('Template not found');
  if (tpl.scope === 'shared') {
    if (req.user.role !== 'admin') throw forbidden('Only admins may modify shared templates');
  } else if (tpl.owner_id !== req.user.id) {
    throw forbidden('Cannot modify another user\'s template');
  }
  const updates = [];
  const params = [];
  if (req.body?.name !== undefined) {
    updates.push('name = ?');
    params.push(requireString(req.body.name, 'name', { min: 1, max: 120 }));
  }
  if (req.body?.body !== undefined) {
    updates.push('body = ?');
    params.push(requireString(req.body.body, 'body', { min: 1, max: 20000 }));
  }
  if (req.body?.description !== undefined) {
    updates.push('description = ?');
    params.push(optionalString(req.body.description, 'description', { min: 0, max: 500 }) || null);
  }
  if (req.body?.scope !== undefined) {
    const next = optionalEnum(req.body.scope, 'scope', ['user', 'shared']);
    if (next === 'shared' && req.user.role !== 'admin') {
      throw forbidden('Only admins may publish shared templates');
    }
    updates.push('scope = ?'); params.push(next);
    if (next === 'shared') { updates.push('owner_id = ?'); params.push(null); }
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(req.params.id);
  db.prepare(`UPDATE prompt_templates SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'prompt_template.update', targetKind: 'prompt_template', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM prompt_templates WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function deleteTemplate(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const tpl = db.prepare(`SELECT * FROM prompt_templates WHERE id = ?`).get(req.params.id);
  if (!tpl) throw notFound('Template not found');
  if (tpl.scope === 'shared' && req.user.role !== 'admin') {
    throw forbidden('Only admins may delete shared templates');
  }
  if (tpl.scope === 'user' && tpl.owner_id !== req.user.id) {
    throw forbidden('Cannot delete another user\'s template');
  }
  db.prepare(`DELETE FROM prompt_templates WHERE id = ?`).run(req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'prompt_template.delete', targetKind: 'prompt_template', targetId: req.params.id,
    outcome: 'success' });
  res.json({ ok: true, data: { id: req.params.id, deleted: true } });
}

export function postTemplates(req, res) { return createTemplate(req, res); }
export function patchTemplates(req, res) { return updateTemplate(req, res); }
export function getTemplates(req, res) { return listTemplates(req, res); }
