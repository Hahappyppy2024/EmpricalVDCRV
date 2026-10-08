import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hashPassword } from '../lib/crypto.js';
import { hasErrors, validate } from '../lib/validate.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';

function userSelect() {
  return `
    SELECT u.id, u.username, u.email, u.display_name, u.status, u.created_at, u.updated_at,
           r.id AS role_id, r.name AS role_name, r.description AS role_description
    FROM users u JOIN roles r ON r.id = u.role_id`;
}

export async function listUserAndRoleManagement(req, res, next) {
  try {
    const db = getDb();
    const { q, role, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];
    if (q) {
      clauses.push('(u.username LIKE @q OR u.email LIKE @q OR u.display_name LIKE @q)');
      params.q = `%${q}%`;
    }
    if (role) {
      clauses.push('r.name = @role');
      params.role = role;
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const total = db.prepare(`SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id = u.role_id ${where}`).get(params).c;
    const users = db.prepare(`${userSelect()} ${where} ORDER BY u.id LIMIT @limit OFFSET @offset`).all(params);
    const roles = db.prepare('SELECT id, name, description FROM roles ORDER BY id').all();
    const history = db.prepare(`
      SELECT um.*, tu.username AS target_username, rp.name AS previous_role, rn.name AS new_role, a.username AS actor_username
      FROM user_and_role_management um
      JOIN users tu ON tu.id = um.target_user_id
      LEFT JOIN roles rp ON rp.id = um.previous_role_id
      LEFT JOIN roles rn ON rn.id = um.new_role_id
      LEFT JOIN users a ON a.id = um.actor_id
      ORDER BY um.id DESC LIMIT 100
    `).all();
    return ok(res, { data: { users, roles, history }, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total } }, 'User and role management data.');
  } catch (err) {
    return next(err);
  }
}

export async function listRoles(req, res, next) {
  try {
    const db = getDb();
    const roles = db.prepare('SELECT id, name, description FROM roles ORDER BY id').all();
    return ok(res, { data: roles }, 'Roles.');
  } catch (err) {
    return next(err);
  }
}

function logRoleChange(db, { targetUserId, previousRoleId, newRoleId, action, actorId, note }) {
  db.prepare(`INSERT INTO user_and_role_management (target_user_id, previous_role_id, new_role_id, action, actor_id, note)
    VALUES (?, ?, ?, ?, ?, ?)`)
    .run(targetUserId, previousRoleId ?? null, newRoleId ?? null, action, actorId ?? null, note || null);
}

export async function createUserAndRoleManagement(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      username: { type: 'string', required: true, minLength: 3, maxLength: 32, pattern: /^[a-zA-Z0-9_.-]+$/, label: 'Username', message: 'Username may only contain letters, numbers, dots, dashes and underscores.' },
      email: { type: 'string', required: true, email: true, maxLength: 120, label: 'Email' },
      password: { type: 'string', required: true, minLength: 6, maxLength: 128, label: 'Password' },
      roleId: { type: 'number', required: true, label: 'Role' },
      displayName: { type: 'string', maxLength: 80, label: 'Display name' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const role = db.prepare('SELECT * FROM roles WHERE id = ?').get(Number(body.roleId));
    if (!role) return next(apiError(400, 'VALIDATION_ERROR', 'Selected role does not exist.'));
    if (db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(body.username, body.email)) {
      return next(apiError(409, 'DUPLICATE_ACCOUNT', 'A user with this username or email already exists.'));
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const userId = db.prepare(`INSERT INTO users (username, email, password_hash, role_id, display_name, status, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, 'active', ?, ?)`)
      .run(body.username, body.email, hashPassword(body.password), role.id, body.displayName || body.username, now, now).lastInsertRowid;

    logRoleChange(db, { targetUserId: userId, previousRoleId: null, newRoleId: role.id, action: 'create', actorId: req.user.id, note: `Created with role ${role.name}` });
    audit(db, { actorId: req.user.id, action: 'create_user', entityType: 'users', entityId: userId, details: `Created ${body.username} with role ${role.name}` });
    broadcast('user:created', { userId, username: body.username, role: role.name });

    const user = db.prepare(`${userSelect()} WHERE u.id = ?`).get(userId);
    return ok(res, { data: user }, 'User created.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchUserAndRoleManagement(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const target = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
    if (!target) return next(apiError(404, 'NOT_FOUND', 'User not found.'));

    const body = req.body || {};
    const errors = validate(body, {
      roleId: { type: 'number', label: 'Role' },
      status: { type: 'string', oneOf: ['active', 'disabled'], label: 'Status' },
      displayName: { type: 'string', maxLength: 80, label: 'Display name' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    if (id === req.user.id) {
      if (body.roleId !== undefined && Number(body.roleId) !== target.role_id) {
        return next(apiError(422, 'SELF_ROLE_CHANGE', 'You cannot change your own role.'));
      }
      if (body.status === 'disabled') {
        return next(apiError(422, 'SELF_DISABLE', 'You cannot disable your own account.'));
      }
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    if (body.roleId !== undefined) {
      const role = db.prepare('SELECT * FROM roles WHERE id = ?').get(Number(body.roleId));
      if (!role) return next(apiError(400, 'VALIDATION_ERROR', 'Selected role does not exist.'));
      db.prepare('UPDATE users SET role_id = ?, updated_at = ? WHERE id = ?').run(role.id, now, id);
      logRoleChange(db, { targetUserId: id, previousRoleId: target.role_id, newRoleId: role.id, action: 'role_change', actorId: req.user.id, note: `Changed role to ${role.name}` });
      audit(db, { actorId: req.user.id, action: 'role_change', entityType: 'users', entityId: id, details: `Changed role of ${target.username} to ${role.name}` });
    }
    if (body.status !== undefined) {
      db.prepare('UPDATE users SET status = ?, updated_at = ? WHERE id = ?').run(body.status, now, id);
      logRoleChange(db, { targetUserId: id, previousRoleId: target.role_id, newRoleId: target.role_id, action: 'status_change', actorId: req.user.id, note: `Set status to ${body.status}` });
      audit(db, { actorId: req.user.id, action: 'status_change', entityType: 'users', entityId: id, details: `Set ${target.username} status to ${body.status}` });
    }
    if (body.displayName !== undefined) {
      db.prepare('UPDATE users SET display_name = ?, updated_at = ? WHERE id = ?').run(body.displayName, now, id);
      audit(db, { actorId: req.user.id, action: 'update_user', entityType: 'users', entityId: id, details: `Updated display name of ${target.username}` });
    }

    broadcast('user:updated', { userId: id, username: target.username });
    const user = db.prepare(`${userSelect()} WHERE u.id = ?`).get(id);
    return ok(res, { data: user }, 'User updated.');
  } catch (err) {
    return next(err);
  }
}
