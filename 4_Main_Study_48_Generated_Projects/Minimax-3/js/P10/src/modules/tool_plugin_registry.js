import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, conflict, forbidden, notFound, unauthorized
} from '../errors.js';
import {
  requireString, optionalString, requireEnum, optionalEnum, optionalNumber
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';

function nowIso() { return new Date().toISOString(); }

export function listPlugins(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const plugins = db.prepare(`
    SELECT id, identifier, name, description, kind, config_schema,
           global_enabled, created_at, updated_at
    FROM tool_plugins ORDER BY name ASC
  `).all();
  const userSettings = db.prepare(`
    SELECT plugin_id, enabled, config_json FROM user_tool_plugins WHERE user_id = ?
  `).all(req.user.id);
  const byId = Object.fromEntries(userSettings.map(s => [s.plugin_id, s]));
  const enriched = plugins.map(p => ({
    ...p,
    user_enabled: byId[p.id] ? byId[p.id].enabled === 1 : false,
    user_config: byId[p.id] ? JSON.parse(byId[p.id].config_json || '{}') : {}
  }));
  res.json({ ok: true, data: { items: enriched, total: enriched.length } });
}

export function createPlugin(req, res) {
  if (!req.user || req.user.role !== 'admin') throw forbidden('Only admins may register plugins');
  const db = getDb();
  const identifier = requireString(req.body?.identifier, 'identifier', { min: 1, max: 64 });
  const name = requireString(req.body?.name, 'name', { min: 1, max: 120 });
  const description = requireString(req.body?.description, 'description', { min: 1, max: 500 });
  const kind = requireEnum(req.body?.kind, 'kind',
    ['retrieval', 'webhook', 'calculator', 'translator', 'time']);
  const configSchema = optionalString(req.body?.config_schema, 'config_schema',
    { min: 0, max: 4000 }) || '{}';
  const existing = db.prepare(`SELECT id FROM tool_plugins WHERE identifier = ?`).get(identifier);
  if (existing) throw conflict('Plugin identifier already exists');
  const id = 'plg_' + crypto.randomBytes(8).toString('hex');
  db.prepare(`INSERT INTO tool_plugins
    (id, identifier, name, description, kind, config_schema, global_enabled, created_at, updated_at)
    VALUES (?,?,?,?,?,?, 1, ?, ?)`).run(id, identifier, name, description, kind, configSchema, nowIso(), nowIso());
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'tool_plugin.create', targetKind: 'tool_plugin', targetId: id, outcome: 'success' });
  const stored = db.prepare(`SELECT * FROM tool_plugins WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function updatePlugin(req, res) {
  if (!req.user || req.user.role !== 'admin') throw forbidden('Only admins may modify plugins');
  const db = getDb();
  const plugin = db.prepare(`SELECT * FROM tool_plugins WHERE id = ?`).get(req.params.id);
  if (!plugin) throw notFound('Plugin not found');
  const updates = [];
  const params = [];
  if (req.body?.name !== undefined) {
    updates.push('name = ?');
    params.push(requireString(req.body.name, 'name', { min: 1, max: 120 }));
  }
  if (req.body?.description !== undefined) {
    updates.push('description = ?');
    params.push(requireString(req.body.description, 'description', { min: 1, max: 500 }));
  }
  if (req.body?.global_enabled !== undefined) {
    updates.push('global_enabled = ?'); params.push(req.body.global_enabled ? 1 : 0);
  }
  if (req.body?.config_schema !== undefined) {
    updates.push('config_schema = ?');
    params.push(optionalString(req.body.config_schema, 'config_schema', { min: 0, max: 4000 }) || '{}');
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(req.params.id);
  db.prepare(`UPDATE tool_plugins SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'tool_plugin.update', targetKind: 'tool_plugin', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM tool_plugins WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function deletePlugin(req, res) {
  if (!req.user || req.user.role !== 'admin') throw forbidden('Only admins may delete plugins');
  const db = getDb();
  const plugin = db.prepare(`SELECT * FROM tool_plugins WHERE id = ?`).get(req.params.id);
  if (!plugin) throw notFound('Plugin not found');
  db.prepare(`DELETE FROM tool_plugins WHERE id = ?`).run(req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'tool_plugin.delete', targetKind: 'tool_plugin', targetId: req.params.id,
    outcome: 'success' });
  res.json({ ok: true, data: { id: req.params.id, deleted: true } });
}

export function setUserPlugin(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const plugin = db.prepare(`SELECT * FROM tool_plugins WHERE id = ?`).get(req.params.id);
  if (!plugin) throw notFound('Plugin not found');
  if (!plugin.global_enabled) throw forbidden('Plugin is globally disabled');
  const enabled = req.body?.enabled ? 1 : 0;
  const configJson = req.body?.config !== undefined
    ? JSON.stringify(req.body.config || {})
    : '{}';
  const existing = db.prepare(`SELECT 1 FROM user_tool_plugins WHERE user_id = ? AND plugin_id = ?`)
    .get(req.user.id, plugin.id);
  if (existing) {
    db.prepare(`UPDATE user_tool_plugins SET enabled = ?, config_json = ?, updated_at = ?
      WHERE user_id = ? AND plugin_id = ?`).run(enabled, configJson, nowIso(), req.user.id, plugin.id);
  } else {
    db.prepare(`INSERT INTO user_tool_plugins
      (user_id, plugin_id, enabled, config_json, created_at, updated_at)
      VALUES (?,?,?,?,?,?)`).run(req.user.id, plugin.id, enabled, configJson, nowIso(), nowIso());
  }
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'tool_plugin.user_toggle', targetKind: 'tool_plugin', targetId: plugin.id,
    outcome: 'success', details: { enabled: !!enabled } });
  res.json({ ok: true, data: { plugin_id: plugin.id, enabled: !!enabled, config: JSON.parse(configJson) } });
}

export function getPlugins(req, res) { return listPlugins(req, res); }
export function postPlugins(req, res) { return createPlugin(req, res); }
export function patchPlugins(req, res) { return updatePlugin(req, res); }
