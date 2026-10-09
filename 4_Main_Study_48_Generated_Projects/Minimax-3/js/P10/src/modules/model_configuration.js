import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { badRequest, forbidden, notFound, unauthorized } from '../errors.js';
import {
  requireString, optionalString, optionalEnum,
  requireNumber, optionalNumber
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';
import { config } from '../config.js';

function newId() { return 'mod_' + crypto.randomBytes(8).toString('hex'); }
function nowIso() { return new Date().toISOString(); }

export function listConfigs(req, res) {
  if (!req.user) throw unauthorized();
  const rows = getDb().prepare(`
    SELECT id, owner_id, model_id, temperature, context_length, safety_mode,
           system_prompt, is_default, created_at, updated_at
    FROM model_configurations
    WHERE owner_id = ?
    ORDER BY is_default DESC, updated_at DESC
  `).all(req.user.id);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function createConfig(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const modelId = requireString(req.body?.model_id || config.defaultModelId, 'model_id',
    { min: 1, max: 80 });
  const temperature = optionalNumber(req.body?.temperature, 'temperature', { min: 0, max: 2 })
    ?? config.defaultTemperature;
  const contextLength = optionalNumber(req.body?.context_length, 'context_length',
    { min: 64, max: 32768, integer: true }) ?? config.defaultContextLength;
  const safetyMode = optionalEnum(req.body?.safety_mode, 'safety_mode',
    ['standard', 'strict', 'off']) ?? 'standard';
  const systemPrompt = optionalString(req.body?.system_prompt, 'system_prompt',
    { min: 0, max: 4000 }) || null;
  const isDefault = req.body?.is_default ? 1 : 0;
  const id = newId();
  db.transaction(() => {
    if (isDefault) {
      db.prepare(`UPDATE model_configurations SET is_default = 0 WHERE owner_id = ?`).run(req.user.id);
    }
    db.prepare(`INSERT INTO model_configurations
      (id, owner_id, model_id, temperature, context_length, safety_mode, system_prompt, is_default, created_at, updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?)`).run(id, req.user.id, modelId, temperature, contextLength,
        safetyMode, systemPrompt, isDefault, nowIso(), nowIso());
  })();
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'model_config.create', targetKind: 'model_config', targetId: id,
    outcome: 'success' });
  const stored = db.prepare(`SELECT * FROM model_configurations WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function updateConfig(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const cfg = db.prepare(`SELECT * FROM model_configurations WHERE id = ?`).get(req.params.id);
  if (!cfg) throw notFound('Configuration not found');
  if (cfg.owner_id !== req.user.id) throw forbidden('Cannot modify another user\'s configuration');
  const updates = [];
  const params = [];
  if (req.body?.model_id !== undefined) {
    updates.push('model_id = ?');
    params.push(requireString(req.body.model_id, 'model_id', { min: 1, max: 80 }));
  }
  if (req.body?.temperature !== undefined) {
    updates.push('temperature = ?');
    params.push(requireNumber(req.body.temperature, 'temperature', { min: 0, max: 2 }));
  }
  if (req.body?.context_length !== undefined) {
    updates.push('context_length = ?');
    params.push(requireNumber(req.body.context_length, 'context_length',
      { min: 64, max: 32768, integer: true }));
  }
  if (req.body?.safety_mode !== undefined) {
    updates.push('safety_mode = ?');
    params.push(optionalEnum(req.body.safety_mode, 'safety_mode', ['standard', 'strict', 'off']));
  }
  if (req.body?.system_prompt !== undefined) {
    updates.push('system_prompt = ?');
    params.push(optionalString(req.body.system_prompt, 'system_prompt', { min: 0, max: 4000 }) || null);
  }
  if (req.body?.is_default !== undefined) {
    if (req.body.is_default) {
      db.prepare(`UPDATE model_configurations SET is_default = 0 WHERE owner_id = ?`).run(req.user.id);
    }
    updates.push('is_default = ?'); params.push(req.body.is_default ? 1 : 0);
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(req.params.id);
  db.prepare(`UPDATE model_configurations SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'model_config.update', targetKind: 'model_config', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM model_configurations WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function deleteConfig(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const cfg = db.prepare(`SELECT * FROM model_configurations WHERE id = ?`).get(req.params.id);
  if (!cfg) throw notFound('Configuration not found');
  if (cfg.owner_id !== req.user.id) throw forbidden('Cannot delete another user\'s configuration');
  if (cfg.is_default) throw badRequest('Cannot delete the default configuration; mark another as default first.');
  db.prepare(`DELETE FROM model_configurations WHERE id = ?`).run(req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'model_config.delete', targetKind: 'model_config', targetId: req.params.id,
    outcome: 'success' });
  res.json({ ok: true, data: { id: req.params.id, deleted: true } });
}

export function postConfigs(req, res) { return createConfig(req, res); }
export function patchConfigs(req, res) { return updateConfig(req, res); }
export function getConfigs(req, res) { return listConfigs(req, res); }
