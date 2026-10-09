import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError } from '../services/errors.js';

const router = Router();

router.get('/plugin_settings_panel', requireAuth, requireRole('admin'), (req, res) => {
  const db = getDb();
  const settings = db.prepare('SELECT * FROM plugin_settings_panel ORDER BY category, key').all();
  res.json({ settings });
});

router.post('/plugin_settings_panel', requireAuth, requireRole('admin'), (req, res) => {
  const { key, value, category } = req.body || {};
  if (!key || typeof key !== 'string') throw validationError('key required', ['key']);
  const db = getDb();
  const existing = db.prepare('SELECT id FROM plugin_settings_panel WHERE key = ?').get(key);
  if (existing) throw validationError('Setting already exists; use PATCH to update', ['key']);
  const info = db.prepare(
    'INSERT INTO plugin_settings_panel (key, value, category, updated_by) VALUES (?, ?, ?, ?)'
  ).run(key, value == null ? '' : String(value), category || 'general', req.user.id);
  const created = db.prepare('SELECT * FROM plugin_settings_panel WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ setting: created, message: 'created' });
});

router.patch('/plugin_settings_panel/:id', requireAuth, requireRole('admin'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const s = db.prepare('SELECT * FROM plugin_settings_panel WHERE id = ?').get(id);
  if (!s) throw notFoundError('Setting not found');
  const updates = [];
  const params = [];
  if (typeof req.body?.value !== 'undefined') { updates.push('value = ?'); params.push(String(req.body.value)); }
  if (typeof req.body?.category === 'string') { updates.push('category = ?'); params.push(req.body.category); }
  if (!updates.length) return res.json({ setting: s });
  updates.push('updated_by = ?'); params.push(req.user.id);
  updates.push("updated_at = datetime('now')");
  params.push(id);
  db.prepare(`UPDATE plugin_settings_panel SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM plugin_settings_panel WHERE id = ?').get(id);
  res.json({ setting: updated, message: 'updated' });
});

export default router;
