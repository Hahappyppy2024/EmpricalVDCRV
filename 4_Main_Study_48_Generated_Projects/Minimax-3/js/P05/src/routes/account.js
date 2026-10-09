import { Router } from 'express';
import { getDb } from '../db/database.js';
import { validationError, authError, notFoundError, conflictError } from '../services/errors.js';
import { verifyPassword, hashPassword } from '../services/password.js';
import { COOKIE_NAME, createSession, destroySession, recordAccess } from '../services/session.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

function validateRegister(body) {
  const { username, email, password, displayName } = body || {};
  const fields = [];
  if (!username || typeof username !== 'string' || username.length < 3) fields.push('username');
  if (!email || typeof email !== 'string' || !email.includes('@')) fields.push('email');
  if (!password || typeof password !== 'string' || password.length < 6) fields.push('password');
  if (!displayName || typeof displayName !== 'string' || displayName.length < 1) fields.push('displayName');
  if (fields.length) throw validationError('Missing or invalid fields', fields);
}

router.get('/account_access', (req, res) => {
  if (req.user) {
    return res.json({ user: sanitizeUser(req.user) });
  }
  return res.json({ user: null });
});

router.post('/account_access', (req, res) => {
  const action = req.query.action || req.body?.action;
  const db = getDb();
  if (action === 'register') {
    validateRegister(req.body);
    const { username, email, password, displayName } = req.body;
    const existing = db.prepare('SELECT id FROM users WHERE username = ? OR email = ?').get(username, email);
    if (existing) throw conflictError('Username or email already in use');
    const visitorRole = db.prepare('SELECT id FROM roles WHERE name = ?').get('visitor');
    const info = db.prepare(
      'INSERT INTO users (username, email, password_hash, display_name, role_id) VALUES (?, ?, ?, ?, ?)'
    ).run(username, email, hashPassword(password), displayName, visitorRole.id);
    const user = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(info.lastInsertRowid);
    const session = createSession(user.id, req);
    res.cookie(COOKIE_NAME, session.id, {
      httpOnly: true,
      sameSite: 'lax',
      maxAge: 1000 * 60 * 60 * 8
    });
    return res.status(201).json({ user: sanitizeUser(user), message: 'registered' });
  }
  if (action === 'login') {
    const { username, password } = req.body || {};
    if (!username || !password) throw validationError('Missing credentials', ['username', 'password']);
    const user = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE username = ? OR email = ?').get(username, username);
    if (!user || !verifyPassword(password, user.password_hash)) {
      throw authError('Invalid credentials');
    }
    if (user.status !== 'active') throw authError('Account inactive');
    const session = createSession(user.id, req);
    res.cookie(COOKIE_NAME, session.id, {
      httpOnly: true,
      sameSite: 'lax',
      maxAge: 1000 * 60 * 60 * 8
    });
    return res.json({ user: sanitizeUser(user), message: 'logged_in' });
  }
  throw validationError('Unknown action', ['action']);
});

router.patch('/account_access/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  if (req.user.id !== id && req.user.role_name !== 'admin') throw authError('Cannot modify other user');
  const { displayName, email, password } = req.body || {};
  const db = getDb();
  const existing = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
  if (!existing) throw notFoundError('User not found');
  const updates = [];
  const params = [];
  if (displayName) { updates.push('display_name = ?'); params.push(displayName); }
  if (email) { updates.push('email = ?'); params.push(email); }
  if (password) { updates.push('password_hash = ?'); params.push(hashPassword(password)); }
  if (!updates.length) return res.json({ user: sanitizeUser(existing) });
  updates.push("updated_at = datetime('now')");
  params.push(id);
  db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(id);
  return res.json({ user: sanitizeUser(updated), message: 'updated' });
});

router.post('/account_access/logout', requireAuth, (req, res) => {
  destroySession(req.sid);
  recordAccess(req.user.id, 'logout', req);
  res.clearCookie(COOKIE_NAME);
  res.json({ message: 'logged_out' });
});

function sanitizeUser(user) {
  return {
    id: user.id,
    username: user.username,
    email: user.email,
    displayName: user.display_name,
    role: user.role_name,
    status: user.status,
    createdAt: user.created_at
  };
}

export default router;
