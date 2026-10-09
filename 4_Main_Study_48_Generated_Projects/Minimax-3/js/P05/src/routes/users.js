import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError, forbidError, conflictError } from '../services/errors.js';
import { hashPassword } from '../services/password.js';
import { logAudit } from '../services/audit.js';

const router = Router();

router.get('/user_and_role_management', requireAuth, requireRole('admin'), (req, res) => {
  const db = getDb();
  const users = db.prepare(`
    SELECT u.id, u.username, u.email, u.display_name, u.status, u.created_at, r.name as role_name, r.id as role_id
    FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id
  `).all();
  const roles = db.prepare('SELECT * FROM roles ORDER BY id').all();
  const history = db.prepare(`
    SELECT h.*, u.username as user_name, r.name as new_role_name, p.username as performed_by_name
    FROM user_and_role_management h
    JOIN users u ON u.id = h.user_id
    LEFT JOIN roles r ON r.id = h.new_role_id
    JOIN users p ON p.id = h.performed_by
    ORDER BY h.id DESC LIMIT 50
  `).all();
  res.json({ users, roles, history });
});

router.post('/user_and_role_management', requireAuth, requireRole('admin'), (req, res) => {
  const action = req.body?.action;
  if (action === 'create_user') {
    const { username, email, displayName, roleName, password } = req.body;
    if (!username || !email || !displayName || !roleName || !password) {
      throw validationError('Missing fields', ['username', 'email', 'displayName', 'roleName', 'password']);
    }
    const db = getDb();
    const existing = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
    if (existing) throw conflictError('Username or email exists');
    const role = db.prepare('SELECT id FROM roles WHERE name = ?').get(roleName);
    if (!role) throw validationError('Invalid role', ['roleName']);
    const info = db.prepare(
      'INSERT INTO users (username, email, password_hash, display_name, role_id) VALUES (?, ?, ?, ?, ?)'
    ).run(username, email, hashPassword(password), displayName, role.id);
    db.prepare('INSERT INTO user_and_role_management (user_id, new_role_id, action, performed_by) VALUES (?, ?, ?, ?)').run(info.lastInsertRowid, role.id, 'created', req.user.id);
    logAudit({ actorId: req.user.id, action: 'user.create', resource: `user:${info.lastInsertRowid}`, details: JSON.stringify({ username, roleName }) });
    const created = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(info.lastInsertRowid);
    return res.status(201).json({ user: sanitize(created), message: 'user_created' });
  }
  if (action === 'assign_role') {
    const { userId, roleName } = req.body;
    if (!userId || !roleName) throw validationError('userId and roleName required');
    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(Number(userId));
    if (!user) throw notFoundError('User not found');
    const role = db.prepare('SELECT id FROM roles WHERE name = ?').get(roleName);
    if (!role) throw validationError('Invalid role', ['roleName']);
    db.prepare("UPDATE users SET role_id = ?, updated_at = datetime('now') WHERE id = ?").run(role.id, user.id);
    db.prepare('INSERT INTO user_and_role_management (user_id, new_role_id, action, performed_by) VALUES (?, ?, ?, ?)').run(user.id, role.id, 'assigned', req.user.id);
    logAudit({ actorId: req.user.id, action: 'user.role_change', resource: `user:${user.id}`, details: JSON.stringify({ roleName }) });
    const updated = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(user.id);
    return res.json({ user: sanitize(updated), message: 'role_assigned' });
  }
  throw validationError('Unknown action', ['action']);
});

router.patch('/user_and_role_management/:id', requireAuth, requireRole('admin'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
  if (!user) throw notFoundError('User not found');
  const updates = [];
  const params = [];
  if (typeof req.body?.displayName === 'string') { updates.push('display_name = ?'); params.push(req.body.displayName); }
  if (typeof req.body?.status === 'string' && ['active', 'suspended'].includes(req.body.status)) {
    updates.push('status = ?'); params.push(req.body.status);
  }
  if (!updates.length) return res.json({ message: 'noop' });
  updates.push("updated_at = datetime('now')");
  params.push(id);
  db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  logAudit({ actorId: req.user.id, action: 'user.update', resource: `user:${id}`, details: JSON.stringify(req.body) });
  const updated = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(id);
  res.json({ user: sanitize(updated), message: 'updated' });
});

function sanitize(u) {
  return {
    id: u.id,
    username: u.username,
    email: u.email,
    displayName: u.display_name,
    role: u.role_name,
    status: u.status,
    createdAt: u.created_at
  };
}

export default router;
