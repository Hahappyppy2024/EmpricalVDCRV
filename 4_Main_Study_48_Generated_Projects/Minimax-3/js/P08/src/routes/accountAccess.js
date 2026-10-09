import express from 'express';
import { db } from '../db/index.js';
import { hashPassword } from '../db/seed.js';
import { getUserByCredentials, findUserByUsername } from '../services/userService.js';
import { createSession, destroySession, getSession, SESSION_COOKIE_NAME } from '../services/sessionService.js';
import { ok, fail, AppError } from '../middleware/http.js';

const router = express.Router();

router.get('/account_access', (req, res) => {
  const user = req.session?.user;
  if (!user) return ok(res, { authenticated: false });
  return ok(res, { authenticated: true, user });
});

router.post('/account_access/login', express.json(), (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return fail(res, 'username and password are required', 400, 'validation_error');
  }
  const candidate = findUserByUsername(String(username).trim());
  if (!candidate || candidate.password_hash !== hashPassword(String(password))) {
    return fail(res, 'Invalid credentials', 401, 'invalid_credentials');
  }
  const session = createSession(candidate.id);
  res.cookie(SESSION_COOKIE_NAME, session.id, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    expires: new Date(session.expiresAt)
  });
  return ok(res, {
    session: { id: session.id, expiresAt: session.expiresAt },
    user: {
      id: candidate.id,
      username: candidate.username,
      email: candidate.email,
      fullName: candidate.full_name,
      role: candidate.role
    }
  });
});

router.post('/account_access/logout', (req, res) => {
  const sid = req.cookies?.[SESSION_COOKIE_NAME];
  destroySession(sid);
  res.clearCookie(SESSION_COOKIE_NAME, { path: '/' });
  return ok(res, { signedOut: true });
});

router.patch('/account_access/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const targetId = Number(req.params.id);
  if (!targetId) return fail(res, 'Invalid id', 400, 'validation_error');
  if (session.user.id !== targetId && session.user.role !== 'admin') {
    return fail(res, 'Cannot modify another user', 403, 'forbidden');
  }
  const { email, fullName, password } = req.body || {};
  const target = db.prepare('SELECT * FROM users WHERE id = ?').get(targetId);
  if (!target) return fail(res, 'User not found', 404, 'not_found');

  const updates = [];
  const values = [];
  if (email) { updates.push('email = ?'); values.push(email); }
  if (fullName) { updates.push('full_name = ?'); values.push(fullName); }
  if (password) { updates.push('password_hash = ?'); values.push(hashPassword(String(password))); }
  if (!updates.length) return fail(res, 'No updatable fields supplied', 400, 'validation_error');
  values.push(targetId);
  db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  return ok(res, { updated: true });
});

export default router;
