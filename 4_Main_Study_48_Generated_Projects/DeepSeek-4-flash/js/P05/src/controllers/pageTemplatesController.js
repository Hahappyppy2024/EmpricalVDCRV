import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { slugify } from '../lib/crypto.js';
import { hasErrors, validate } from '../lib/validate.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';

const EDITORS = ['editor', 'admin'];

function templateSelect() {
  return `
    SELECT t.*, u.username AS created_by_username
    FROM page_templates t
    LEFT JOIN users u ON u.id = t.created_by`;
}

function uniqueTemplateSlug(db, base, ignoreId = null) {
  let candidate = base || 'template';
  let i = 2;
  const stmt = db.prepare('SELECT id FROM page_templates WHERE slug = ?' + (ignoreId ? ' AND id != ?' : ''));
  while (true) {
    const row = ignoreId ? stmt.get(candidate, ignoreId) : stmt.get(candidate);
    if (!row) return candidate;
    candidate = `${base || 'template'}-${i}`;
    i += 1;
  }
}

export async function getPageTemplates(req, res, next) {
  try {
    const db = getDb();
    const templates = db.prepare(`${templateSelect()} ORDER BY t.id`).all();
    const menus = db.prepare('SELECT * FROM nav_menus ORDER BY position, id').all();
    return ok(res, { data: { templates, menus } }, 'Page templates and navigation menus.');
  } catch (err) {
    return next(err);
  }
}

export async function createPageTemplate(req, res, next) {
  try {
    if (!EDITORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only editors and admins can create page templates.'));
    }
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      name: { type: 'string', required: true, minLength: 2, maxLength: 100, label: 'Name' },
      description: { type: 'string', maxLength: 300, label: 'Description' },
      body: { type: 'string', maxLength: 10000, label: 'Template body' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    if (db.prepare('SELECT id FROM page_templates WHERE name = ?').get(body.name)) {
      return next(apiError(409, 'DUPLICATE_TEMPLATE', 'A template with this name already exists.'));
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const slug = body.slug ? slugify(body.slug) : slugify(body.name);
    const id = db.prepare(`INSERT INTO page_templates (name, slug, description, body, created_by, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?)`)
      .run(body.name, uniqueTemplateSlug(db, slug), body.description || null, body.body || '', req.user.id, now, now).lastInsertRowid;

    audit(db, { actorId: req.user.id, action: 'create_template', entityType: 'page_templates', entityId: id, details: `Created template "${body.name}"` });
    broadcast('template:created', { templateId: id, name: body.name });
    const template = db.prepare(`${templateSelect()} WHERE t.id = ?`).get(id);
    return ok(res, { data: template }, 'Page template created.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchPageTemplate(req, res, next) {
  try {
    if (!EDITORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only editors and admins can update page templates.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM page_templates WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Page template not found.'));

    const body = req.body || {};
    const errors = validate(body, {
      name: { type: 'string', minLength: 2, maxLength: 100, label: 'Name' },
      description: { type: 'string', maxLength: 300, label: 'Description' },
      body: { type: 'string', maxLength: 10000, label: 'Template body' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const sets = ['updated_at = ?'];
    const params = [now];
    if (body.name !== undefined) {
      sets.push('name = ?');
      params.push(body.name);
      if (body.slug !== undefined) {
        sets.push('slug = ?');
        params.push(uniqueTemplateSlug(db, slugify(body.slug), id));
      }
    }
    if (body.description !== undefined) {
      sets.push('description = ?');
      params.push(body.description || null);
    }
    if (body.body !== undefined) {
      sets.push('body = ?');
      params.push(body.body);
    }
    params.push(id);
    db.prepare(`UPDATE page_templates SET ${sets.join(', ')} WHERE id = ?`).run(...params);

    audit(db, { actorId: req.user.id, action: 'update_template', entityType: 'page_templates', entityId: id, details: 'Updated template' });
    const template = db.prepare(`${templateSelect()} WHERE t.id = ?`).get(id);
    return ok(res, { data: template }, 'Page template updated.');
  } catch (err) {
    return next(err);
  }
}

export async function deletePageTemplate(req, res, next) {
  try {
    if (req.user.role_name !== 'admin') {
      return next(apiError(403, 'FORBIDDEN', 'Only admins can delete page templates.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM page_templates WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Page template not found.'));
    db.prepare('UPDATE articles SET template_id = NULL WHERE template_id = ?').run(id);
    db.prepare('DELETE FROM page_templates WHERE id = ?').run(id);
    audit(db, { actorId: req.user.id, action: 'delete_template', entityType: 'page_templates', entityId: id, details: `Deleted template "${existing.name}"` });
    return ok(res, { data: { id, deleted: true } }, 'Page template deleted.');
  } catch (err) {
    return next(err);
  }
}

// ---- Navigation menus ----

export async function createNavMenu(req, res, next) {
  try {
    if (!EDITORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only editors and admins can manage navigation menus.'));
    }
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      label: { type: 'string', required: true, maxLength: 80, label: 'Label' },
      url: { type: 'string', required: true, maxLength: 200, label: 'URL' },
      position: { type: 'number', label: 'Position' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const id = db.prepare(`INSERT INTO nav_menus (label, url, position) VALUES (?, ?, ?)`)
      .run(body.label, body.url, Number(body.position) || 0).lastInsertRowid;
    const menu = db.prepare('SELECT * FROM nav_menus WHERE id = ?').get(id);
    broadcast('menu:updated', {});
    return ok(res, { data: menu }, 'Navigation menu created.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchNavMenu(req, res, next) {
  try {
    if (!EDITORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only editors and admins can manage navigation menus.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM nav_menus WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Navigation menu not found.'));

    const body = req.body || {};
    const sets = [];
    const params = [];
    if (body.label !== undefined) {
      if (typeof body.label !== 'string' || !body.label.trim()) return next(apiError(400, 'VALIDATION_ERROR', 'Label is required.'));
      sets.push('label = ?');
      params.push(body.label);
    }
    if (body.url !== undefined) {
      if (typeof body.url !== 'string' || !body.url.trim()) return next(apiError(400, 'VALIDATION_ERROR', 'URL is required.'));
      sets.push('url = ?');
      params.push(body.url);
    }
    if (body.position !== undefined) {
      sets.push('position = ?');
      params.push(Number(body.position) || 0);
    }
    if (sets.length) {
      params.push(id);
      db.prepare(`UPDATE nav_menus SET ${sets.join(', ')} WHERE id = ?`).run(...params);
    }
    broadcast('menu:updated', {});
    const menu = db.prepare('SELECT * FROM nav_menus WHERE id = ?').get(id);
    return ok(res, { data: menu }, 'Navigation menu updated.');
  } catch (err) {
    return next(err);
  }
}

export async function deleteNavMenu(req, res, next) {
  try {
    if (req.user.role_name !== 'admin') {
      return next(apiError(403, 'FORBIDDEN', 'Only admins can delete navigation menus.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM nav_menus WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Navigation menu not found.'));
    db.prepare('DELETE FROM nav_menus WHERE id = ?').run(id);
    broadcast('menu:updated', {});
    return ok(res, { data: { id, deleted: true } }, 'Navigation menu deleted.');
  } catch (err) {
    return next(err);
  }
}
