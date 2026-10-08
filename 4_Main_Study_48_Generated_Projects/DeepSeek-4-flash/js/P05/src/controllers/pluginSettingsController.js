import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';

export async function listPluginSettingsPanel(req, res, next) {
  try {
    const db = getDb();
    const rows = db.prepare(`
      SELECT s.*, u.username AS updated_by_username
      FROM plugin_settings_panel s
      LEFT JOIN users u ON u.id = s.updated_by
      ORDER BY s.id
    `).all();
    return ok(res, { data: rows }, 'Plugin/settings panel.');
  } catch (err) {
    return next(err);
  }
}

export async function createPluginSetting(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      key: { type: 'string', required: true, minLength: 2, maxLength: 80, pattern: /^[a-z0-9_]+$/, label: 'Key', message: 'Key may only contain lowercase letters, numbers and underscores.' },
      value: { type: 'string', required: true, maxLength: 2000, label: 'Value' },
      description: { type: 'string', maxLength: 300, label: 'Description' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    if (db.prepare('SELECT id FROM plugin_settings_panel WHERE key = ?').get(body.key)) {
      return next(apiError(409, 'DUPLICATE_SETTING', 'A setting with this key already exists.'));
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const id = db.prepare(`INSERT INTO plugin_settings_panel (key, value, description, updated_by, updated_at)
      VALUES (?, ?, ?, ?, ?)`)
      .run(body.key, body.value, body.description || null, req.user.id, now).lastInsertRowid;

    audit(db, { actorId: req.user.id, action: 'create_setting', entityType: 'plugin_settings_panel', entityId: id, details: `Created setting ${body.key}` });
    broadcast('settings:updated', { key: body.key, value: body.value });

    const row = db.prepare(`
      SELECT s.*, u.username AS updated_by_username
      FROM plugin_settings_panel s LEFT JOIN users u ON u.id = s.updated_by WHERE s.id = ?
    `).get(id);
    return ok(res, { data: row }, 'Setting created.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchPluginSetting(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM plugin_settings_panel WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Setting not found.'));

    const body = req.body || {};
    const errors = validate(body, {
      value: { type: 'string', required: true, maxLength: 2000, label: 'Value' },
      description: { type: 'string', maxLength: 300, label: 'Description' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    db.prepare(`UPDATE plugin_settings_panel SET value = ?, description = COALESCE(?, description), updated_by = ?, updated_at = ? WHERE id = ?`)
      .run(body.value, body.description, req.user.id, now, id);

    audit(db, { actorId: req.user.id, action: 'update_setting', entityType: 'plugin_settings_panel', entityId: id, details: `Updated ${existing.key}` });
    broadcast('settings:updated', { key: existing.key, value: body.value });

    const row = db.prepare(`
      SELECT s.*, u.username AS updated_by_username
      FROM plugin_settings_panel s LEFT JOIN users u ON u.id = s.updated_by WHERE s.id = ?
    `).get(id);
    return ok(res, { data: row }, 'Setting updated.');
  } catch (err) {
    return next(err);
  }
}

export async function deletePluginSetting(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM plugin_settings_panel WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Setting not found.'));

    if (['site_name', 'allow_comments', 'moderate_comments'].includes(existing.key)) {
      return next(apiError(422, 'PROTECTED_SETTING', 'This protected setting cannot be deleted.'));
    }
    db.prepare('DELETE FROM plugin_settings_panel WHERE id = ?').run(id);
    audit(db, { actorId: req.user.id, action: 'delete_setting', entityType: 'plugin_settings_panel', entityId: id, details: `Deleted setting ${existing.key}` });
    return ok(res, { data: { id, deleted: true } }, 'Setting deleted.');
  } catch (err) {
    return next(err);
  }
}
