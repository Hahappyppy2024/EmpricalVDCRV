import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError } from '../services/errors.js';

const router = Router();

router.get('/page_templates', requireAuth, (req, res) => {
  const db = getDb();
  const templates = db.prepare('SELECT * FROM page_templates ORDER BY id').all();
  const menus = db.prepare('SELECT * FROM navigation_menus ORDER BY sort_order').all();
  res.json({ templates: templates.map(serialize), menus });
});

router.post('/page_templates', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const { name, description, layoutHtml, regions, isDefault } = req.body || {};
  if (!name || typeof name !== 'string') throw validationError('name required', ['name']);
  if (!layoutHtml || typeof layoutHtml !== 'string') throw validationError('layoutHtml required', ['layoutHtml']);
  const db = getDb();
  if (isDefault) {
    db.prepare('UPDATE page_templates SET is_default = 0').run();
  }
  const info = db.prepare(
    'INSERT INTO page_templates (name, description, layout_html, regions, is_default) VALUES (?, ?, ?, ?, ?)'
  ).run(name, description || '', layoutHtml, JSON.stringify(regions || ['main']), isDefault ? 1 : 0);
  const created = db.prepare('SELECT * FROM page_templates WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ template: serialize(created), message: 'created' });
});

router.patch('/page_templates/:id', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const tpl = db.prepare('SELECT * FROM page_templates WHERE id = ?').get(id);
  if (!tpl) throw notFoundError('Template not found');
  const updates = [];
  const params = [];
  if (typeof req.body?.name === 'string') { updates.push('name = ?'); params.push(req.body.name); }
  if (typeof req.body?.description === 'string') { updates.push('description = ?'); params.push(req.body.description); }
  if (typeof req.body?.layoutHtml === 'string') { updates.push('layout_html = ?'); params.push(req.body.layoutHtml); }
  if (Array.isArray(req.body?.regions)) { updates.push('regions = ?'); params.push(JSON.stringify(req.body.regions)); }
  if (typeof req.body?.isDefault === 'boolean') {
    if (req.body.isDefault) db.prepare('UPDATE page_templates SET is_default = 0').run();
    updates.push('is_default = ?'); params.push(req.body.isDefault ? 1 : 0);
  }
  if (!updates.length) return res.json({ template: serialize(tpl) });
  params.push(id);
  db.prepare(`UPDATE page_templates SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM page_templates WHERE id = ?').get(id);
  res.json({ template: serialize(updated), message: 'updated' });
});

router.post('/page_templates/menus', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const { label, url, sortOrder, parentId } = req.body || {};
  if (!label || !url) throw validationError('label and url required', ['label', 'url']);
  const db = getDb();
  const info = db.prepare('INSERT INTO navigation_menus (label, url, sort_order, parent_id) VALUES (?, ?, ?, ?)').run(label, url, Number(sortOrder || 0), parentId || null);
  const created = db.prepare('SELECT * FROM navigation_menus WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ menu: created, message: 'created' });
});

router.patch('/page_templates/menus/:id', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const menu = db.prepare('SELECT * FROM navigation_menus WHERE id = ?').get(id);
  if (!menu) throw notFoundError('Menu item not found');
  const updates = [];
  const params = [];
  if (typeof req.body?.label === 'string') { updates.push('label = ?'); params.push(req.body.label); }
  if (typeof req.body?.url === 'string') { updates.push('url = ?'); params.push(req.body.url); }
  if (typeof req.body?.sortOrder === 'number') { updates.push('sort_order = ?'); params.push(req.body.sortOrder); }
  if (!updates.length) return res.json({ menu });
  params.push(id);
  db.prepare(`UPDATE navigation_menus SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM navigation_menus WHERE id = ?').get(id);
  res.json({ menu: updated, message: 'updated' });
});

router.delete('/page_templates/menus/:id', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  db.prepare('DELETE FROM navigation_menus WHERE id = ?').run(id);
  res.json({ message: 'deleted', id });
});

function serialize(t) {
  return {
    id: t.id,
    name: t.name,
    description: t.description,
    layoutHtml: t.layout_html,
    regions: safeParseJson(t.regions, []),
    isDefault: !!t.is_default,
    createdAt: t.created_at
  };
}

function safeParseJson(s, fallback) {
  try { return JSON.parse(s); } catch { return fallback; }
}

export default router;
