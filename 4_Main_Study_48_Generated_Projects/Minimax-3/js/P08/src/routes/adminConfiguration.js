import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';
import { hashPassword } from '../db/seed.js';
import { recordAudit } from '../services/userService.js';

const router = express.Router();

function requireAdmin(req, res, next) {
  if (!req.session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (req.session.user.role !== 'admin') return fail(res, 'Admin role required', 403, 'forbidden');
  next();
}

router.get('/admin_configuration', requireAdmin, (req, res) => {
  const departments = db.prepare('SELECT * FROM departments ORDER BY name').all();
  const categories = db.prepare('SELECT * FROM categories ORDER BY name').all();
  const configs = db.prepare('SELECT * FROM admin_configurations ORDER BY config_key').all();
  const users = db.prepare(`
    SELECT u.id, u.username, u.email, u.full_name, u.role, u.department_id, u.manager_id,
           d.name AS department_name
    FROM users u LEFT JOIN departments d ON d.id = u.department_id
    ORDER BY u.role, u.username
  `).all();
  const audit = db.prepare(`
    SELECT a.*, u.username FROM audit_events a
    LEFT JOIN users u ON u.id = a.actor_id
    ORDER BY a.created_at DESC LIMIT 100
  `).all();
  return ok(res, { departments, categories, configs, users, audit });
});

router.post('/admin_configuration/departments', requireAdmin, express.json(), (req, res) => {
  const { name, code, costCenter } = req.body || {};
  if (!name || !code || !costCenter) return fail(res, 'name, code, costCenter required', 400, 'validation_error');
  try {
    const info = db.prepare(`INSERT INTO departments (name, code, cost_center) VALUES (?, ?, ?)`).run(name, code, costCenter);
    recordAudit(req.session.user.id, 'department', info.lastInsertRowid, 'create', { name, code, costCenter });
    return ok(res, { id: info.lastInsertRowid }, 201);
  } catch (e) {
    return fail(res, e.message, 400, 'validation_error');
  }
});

router.patch('/admin_configuration/departments/:id', requireAdmin, express.json(), (req, res) => {
  const id = Number(req.params.id);
  const dept = db.prepare('SELECT * FROM departments WHERE id = ?').get(id);
  if (!dept) return fail(res, 'Not found', 404, 'not_found');
  const { name, code, costCenter } = req.body || {};
  const updates = []; const values = [];
  if (name) { updates.push('name = ?'); values.push(name); }
  if (code) { updates.push('code = ?'); values.push(code); }
  if (costCenter) { updates.push('cost_center = ?'); values.push(costCenter); }
  if (!updates.length) return fail(res, 'Nothing to update', 400, 'validation_error');
  values.push(id);
  db.prepare(`UPDATE departments SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  recordAudit(req.session.user.id, 'department', id, 'update', req.body);
  return ok(res, { updated: true });
});

router.post('/admin_configuration/categories', requireAdmin, express.json(), (req, res) => {
  const { name, code, description } = req.body || {};
  if (!name || !code) return fail(res, 'name and code required', 400, 'validation_error');
  try {
    const info = db.prepare(`INSERT INTO categories (name, code, description) VALUES (?, ?, ?)`).run(name, code, description || null);
    recordAudit(req.session.user.id, 'category', info.lastInsertRowid, 'create', { name, code });
    return ok(res, { id: info.lastInsertRowid }, 201);
  } catch (e) {
    return fail(res, e.message, 400, 'validation_error');
  }
});

router.patch('/admin_configuration/categories/:id', requireAdmin, express.json(), (req, res) => {
  const id = Number(req.params.id);
  const cat = db.prepare('SELECT * FROM categories WHERE id = ?').get(id);
  if (!cat) return fail(res, 'Not found', 404, 'not_found');
  const { name, code, description } = req.body || {};
  const updates = []; const values = [];
  if (name) { updates.push('name = ?'); values.push(name); }
  if (code) { updates.push('code = ?'); values.push(code); }
  if (description !== undefined) { updates.push('description = ?'); values.push(description); }
  if (!updates.length) return fail(res, 'Nothing to update', 400, 'validation_error');
  values.push(id);
  db.prepare(`UPDATE categories SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  recordAudit(req.session.user.id, 'category', id, 'update', req.body);
  return ok(res, { updated: true });
});

router.post('/admin_configuration/users', requireAdmin, express.json(), (req, res) => {
  const { username, email, fullName, password, role, departmentId, managerId } = req.body || {};
  if (!username || !email || !fullName || !password || !role) {
    return fail(res, 'username, email, fullName, password, role required', 400, 'validation_error');
  }
  if (!['employee','manager','finance','admin'].includes(role)) {
    return fail(res, 'Invalid role', 400, 'validation_error');
  }
  try {
    const info = db.prepare(`
      INSERT INTO users (username, email, full_name, password_hash, role, department_id, manager_id)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `).run(username, email, fullName, hashPassword(String(password)), role, departmentId || null, managerId || null);
    recordAudit(req.session.user.id, 'user', info.lastInsertRowid, 'create', { username, role });
    return ok(res, { id: info.lastInsertRowid }, 201);
  } catch (e) {
    return fail(res, e.message, 400, 'validation_error');
  }
});

router.patch('/admin_configuration/users/:id', requireAdmin, express.json(), (req, res) => {
  const id = Number(req.params.id);
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
  if (!user) return fail(res, 'Not found', 404, 'not_found');
  const { email, fullName, role, departmentId, managerId, password } = req.body || {};
  const updates = []; const values = [];
  if (email) { updates.push('email = ?'); values.push(email); }
  if (fullName) { updates.push('full_name = ?'); values.push(fullName); }
  if (role) {
    if (!['employee','manager','finance','admin'].includes(role)) return fail(res, 'Invalid role', 400, 'validation_error');
    updates.push('role = ?'); values.push(role);
  }
  if (departmentId !== undefined) { updates.push('department_id = ?'); values.push(departmentId || null); }
  if (managerId !== undefined) { updates.push('manager_id = ?'); values.push(managerId || null); }
  if (password) { updates.push('password_hash = ?'); values.push(hashPassword(String(password))); }
  if (!updates.length) return fail(res, 'Nothing to update', 400, 'validation_error');
  values.push(id);
  db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  recordAudit(req.session.user.id, 'user', id, 'update', req.body);
  return ok(res, { updated: true });
});

router.put('/admin_configuration/config/:key', requireAdmin, express.json(), (req, res) => {
  const { value, description } = req.body || {};
  if (value === undefined) return fail(res, 'value required', 400, 'validation_error');
  const existing = db.prepare('SELECT * FROM admin_configurations WHERE config_key = ?').get(req.params.key);
  if (existing) {
    db.prepare(`
      UPDATE admin_configurations SET config_value = ?, description = COALESCE(?, description), updated_by = ?, updated_at = datetime('now')
      WHERE config_key = ?
    `).run(String(value), description ?? null, req.session.user.id, req.params.key);
    recordAudit(req.session.user.id, 'config', existing.id, 'update', { key: req.params.key, value });
  } else {
    db.prepare(`
      INSERT INTO admin_configurations (config_key, config_value, description, updated_by)
      VALUES (?, ?, ?, ?)
    `).run(req.params.key, String(value), description || null, req.session.user.id);
    recordAudit(req.session.user.id, 'config', null, 'create', { key: req.params.key, value });
  }
  return ok(res, { updated: true });
});

export default router;
