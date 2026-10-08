import { getDb } from '../db/database.js';
import { badRequest, forbidden, notFound } from '../lib/errors.js';
import { writeAudit } from './auditService.js';
import { hashPassword } from '../lib/crypto.js';
import { publicUser, findUserById } from './authService.js';
import { ROLES } from './authService.js';

export function adminOverview(db) {
  return {
    departments: db.prepare('SELECT * FROM departments ORDER BY id').all(),
    cost_centers: db
      .prepare(
        `SELECT c.*, d.name AS department_name FROM cost_centers c LEFT JOIN departments d ON d.id = c.department_id ORDER BY c.id`
      )
      .all(),
    categories: db.prepare('SELECT * FROM categories ORDER BY id').all(),
    roles: ROLES,
    users: db
      .prepare(
        `SELECT u.id, u.username, u.full_name, u.email, u.role, u.department_id, u.cost_center_id, u.manager_id, u.active,
                d.name AS department_name, c.name AS cost_center_name, m.full_name AS manager_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN cost_centers c ON c.id = u.cost_center_id
         LEFT JOIN users m ON m.id = u.manager_id
         ORDER BY u.id`
      )
      .all(),
  };
}

function assertAdmin(user) {
  if (user.role !== 'admin') throw forbidden('Only admin can configure the system');
}

export function createConfiguration(db, user, { type, ...payload }) {
  assertAdmin(user);
  const now = () => new Date().toISOString().replace('T', ' ').slice(0, 19);

  if (type === 'department') {
    const { code, name } = payload;
    if (!code || !name) throw badRequest('code and name are required');
    if (db.prepare('SELECT id FROM departments WHERE code = ?').get(code)) throw badRequest('Department code already exists');
    const info = db.prepare('INSERT INTO departments (code, name, created_at) VALUES (?, ?, ?)').run(String(code), String(name), now());
    writeAudit(db, { actorId: user.id, action: 'admin.department_created', entityType: 'department', entityId: info.lastInsertRowid, details: { code } });
    return db.prepare('SELECT * FROM departments WHERE id = ?').get(info.lastInsertRowid);
  }

  if (type === 'cost_center') {
    const { code, name, departmentId } = payload;
    if (!code || !name) throw badRequest('code and name are required');
    if (departmentId && !db.prepare('SELECT id FROM departments WHERE id = ?').get(departmentId)) throw badRequest('Invalid department_id');
    if (db.prepare('SELECT id FROM cost_centers WHERE code = ?').get(code)) throw badRequest('Cost center code already exists');
    const info = db.prepare('INSERT INTO cost_centers (code, name, department_id, created_at) VALUES (?, ?, ?, ?)').run(String(code), String(name), departmentId || null, now());
    writeAudit(db, { actorId: user.id, action: 'admin.cost_center_created', entityType: 'cost_center', entityId: info.lastInsertRowid, details: { code } });
    return db.prepare('SELECT * FROM cost_centers WHERE id = ?').get(info.lastInsertRowid);
  }

  if (type === 'category') {
    const { code, name, requiresReceipt } = payload;
    if (!code || !name) throw badRequest('code and name are required');
    if (db.prepare('SELECT id FROM categories WHERE code = ?').get(code)) throw badRequest('Category code already exists');
    const info = db.prepare('INSERT INTO categories (code, name, requires_receipt, created_at) VALUES (?, ?, ?, ?)').run(String(code), String(name), requiresReceipt ? 1 : 0, now());
    writeAudit(db, { actorId: user.id, action: 'admin.category_created', entityType: 'category', entityId: info.lastInsertRowid, details: { code } });
    return db.prepare('SELECT * FROM categories WHERE id = ?').get(info.lastInsertRowid);
  }

  if (type === 'user') {
    const { username, password, fullName, email, role, departmentId, costCenterId, managerId } = payload;
    if (!username || !password || !fullName || !email) throw badRequest('username, password, full_name and email are required');
    if (!ROLES.includes(role)) throw badRequest('Invalid role');
    if (db.prepare('SELECT id FROM users WHERE username = ?').get(username)) throw badRequest('Username already exists');
    const info = db
      .prepare(
        `INSERT INTO users (username, password_hash, full_name, email, role, department_id, cost_center_id, manager_id, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)`
      )
      .run(String(username), hashPassword(password), String(fullName), String(email), role, departmentId || null, costCenterId || null, managerId || null, now());
    writeAudit(db, { actorId: user.id, action: 'admin.user_created', entityType: 'user', entityId: info.lastInsertRowid, details: { username, role } });
    return publicUser(findUserById(db, info.lastInsertRowid));
  }

  throw badRequest('Unsupported configuration type');
}

export function updateConfiguration(db, user, id, { type, ...patch }) {
  assertAdmin(user);

  if (type === 'department') {
    const current = db.prepare('SELECT * FROM departments WHERE id = ?').get(id);
    if (!current) throw notFound('Department not found');
    if (patch.name !== undefined) {
      if (!String(patch.name).trim()) throw badRequest('name cannot be empty');
      db.prepare('UPDATE departments SET name = ? WHERE id = ?').run(String(patch.name).trim(), id);
    }
    writeAudit(db, { actorId: user.id, action: 'admin.department_updated', entityType: 'department', entityId: id });
    return db.prepare('SELECT * FROM departments WHERE id = ?').get(id);
  }

  if (type === 'cost_center') {
    const current = db.prepare('SELECT * FROM cost_centers WHERE id = ?').get(id);
    if (!current) throw notFound('Cost center not found');
    const fields = [];
    const params = [];
    if (patch.name !== undefined) {
      if (!String(patch.name).trim()) throw badRequest('name cannot be empty');
      fields.push('name = ?');
      params.push(String(patch.name).trim());
    }
    if (patch.department_id !== undefined) {
      if (patch.department_id && !db.prepare('SELECT id FROM departments WHERE id = ?').get(patch.department_id)) throw badRequest('Invalid department_id');
      fields.push('department_id = ?');
      params.push(patch.department_id || null);
    }
    if (fields.length) {
      params.push(id);
      db.prepare(`UPDATE cost_centers SET ${fields.join(', ')} WHERE id = ?`).run(...params);
    }
    writeAudit(db, { actorId: user.id, action: 'admin.cost_center_updated', entityType: 'cost_center', entityId: id });
    return db.prepare('SELECT * FROM cost_centers WHERE id = ?').get(id);
  }

  if (type === 'category') {
    const current = db.prepare('SELECT * FROM categories WHERE id = ?').get(id);
    if (!current) throw notFound('Category not found');
    const fields = [];
    const params = [];
    if (patch.name !== undefined) {
      if (!String(patch.name).trim()) throw badRequest('name cannot be empty');
      fields.push('name = ?');
      params.push(String(patch.name).trim());
    }
    if (patch.requires_receipt !== undefined) {
      fields.push('requires_receipt = ?');
      params.push(patch.requires_receipt ? 1 : 0);
    }
    if (fields.length) {
      params.push(id);
      db.prepare(`UPDATE categories SET ${fields.join(', ')} WHERE id = ?`).run(...params);
    }
    writeAudit(db, { actorId: user.id, action: 'admin.category_updated', entityType: 'category', entityId: id });
    return db.prepare('SELECT * FROM categories WHERE id = ?').get(id);
  }

  if (type === 'user') {
    const current = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
    if (!current) throw notFound('User not found');
    const fields = [];
    const params = [];
    if (patch.role !== undefined) {
      if (!ROLES.includes(patch.role)) throw badRequest('Invalid role');
      fields.push('role = ?');
      params.push(patch.role);
    }
    if (patch.full_name !== undefined) {
      if (!String(patch.full_name).trim()) throw badRequest('full_name cannot be empty');
      fields.push('full_name = ?');
      params.push(String(patch.full_name).trim());
    }
    if (patch.department_id !== undefined) {
      fields.push('department_id = ?');
      params.push(patch.department_id || null);
    }
    if (patch.manager_id !== undefined) {
      fields.push('manager_id = ?');
      params.push(patch.manager_id || null);
    }
    if (patch.active !== undefined) {
      fields.push('active = ?');
      params.push(patch.active ? 1 : 0);
    }
    if (patch.password !== undefined) {
      if (String(patch.password).length < 6) throw badRequest('Password must be at least 6 characters');
      fields.push('password_hash = ?');
      params.push(hashPassword(patch.password));
    }
    if (fields.length) {
      params.push(id);
      db.prepare(`UPDATE users SET ${fields.join(', ')} WHERE id = ?`).run(...params);
    }
    writeAudit(db, { actorId: user.id, action: 'admin.user_updated', entityType: 'user', entityId: id });
    return publicUser(findUserById(db, id));
  }

  throw badRequest('Unsupported configuration type');
}
