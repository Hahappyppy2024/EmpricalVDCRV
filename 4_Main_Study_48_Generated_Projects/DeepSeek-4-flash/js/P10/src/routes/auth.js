import { Router } from 'express';
import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
import { HttpError } from '../middleware/error.js';
import { requireAuth } from '../middleware/auth.js';
import { config } from '../config.js';

export function authRouter(db, { audit, settings }) {
  const router = Router();

  const insertUser = db.prepare(
    `INSERT INTO users (username, email, password_hash, role, status, created_at)
     VALUES (?, ?, ?, 'user', 'active', datetime('now'))`
  );
  const findByUsername = db.prepare('SELECT * FROM users WHERE username = ?');
  const findByEmail = db.prepare('SELECT * FROM users WHERE email = ?');
  const insertSession = db.prepare(
    `INSERT INTO sessions (token, user_id, created_at, expires_at) VALUES (?, ?, datetime('now'), ?)`
  );
  const deleteSession = db.prepare('DELETE FROM sessions WHERE token = ?');
  const insertAccess = db.prepare(
    `INSERT INTO account_access (user_id, action, detail, ip, created_at) VALUES (?, ?, ?, ?, datetime('now'))`
  );
  const getSession = db.prepare('SELECT * FROM sessions WHERE token = ?');

  const setSessionCookie = (res, token) => {
    res.cookie(config.sessionCookieName, token, {
      httpOnly: true,
      sameSite: 'lax',
      path: '/',
      maxAge: config.sessionTtlMs,
    });
  };
  const clearSessionCookie = (res) => {
    res.clearCookie(config.sessionCookieName, { path: '/' });
  };

  router.post('/register', (req, res, next) => {
    try {
      const { username, email, password } = req.body ?? {};
      if (!username || !email || !password) {
        throw new HttpError(400, 'username, email, and password are required', 'VALIDATION_ERROR');
      }
      if (!settings.get('allow_registration')) {
        throw new HttpError(403, 'Registration is disabled by the administrator', 'REGISTRATION_DISABLED');
      }
      if (!/^[\w.-]{3,}$/.test(username)) {
        throw new HttpError(400, 'Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores', 'VALIDATION_ERROR');
      }
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
        throw new HttpError(400, 'A valid email address is required', 'VALIDATION_ERROR');
      }
      if (String(password).length < 6) {
        throw new HttpError(400, 'Password must be at least 6 characters', 'VALIDATION_ERROR');
      }
      if (findByUsername.get(username) || findByEmail.get(email)) {
        throw new HttpError(409, 'Username or email already registered', 'DUPLICATE_ACCOUNT');
      }
      const hash = bcrypt.hashSync(String(password), 10);
      const userId = insertUser.run(username, email, hash).lastInsertRowid;
      const token = crypto.randomBytes(32).toString('hex');
      const expiresAt = new Date(Date.now() + config.sessionTtlMs).toISOString();
      insertSession.run(token, userId, expiresAt);
      insertAccess.run(userId, 'register', 'account registration', req.ip ?? null);
      audit.record(userId, 'register', 'account_access', { username });
      setSessionCookie(res, token);
      res.status(201).json({ ok: true, user: { id: userId, username, email, role: 'user' } });
    } catch (err) {
      next(err);
    }
  });

  router.post('/login', (req, res, next) => {
    try {
      const { username, password } = req.body ?? {};
      if (!username || !password) {
        throw new HttpError(400, 'username and password are required', 'VALIDATION_ERROR');
      }
      const user = findByUsername.get(username) ?? findByEmail.get(username);
      if (!user || !bcrypt.compareSync(String(password), user.password_hash)) {
        throw new HttpError(401, 'Invalid credentials', 'INVALID_CREDENTIALS');
      }
      if (user.status === 'suspended') {
        throw new HttpError(403, 'Account is suspended', 'ACCOUNT_SUSPENDED');
      }
      const token = crypto.randomBytes(32).toString('hex');
      const expiresAt = new Date(Date.now() + config.sessionTtlMs).toISOString();
      insertSession.run(token, user.id, expiresAt);
      insertAccess.run(user.id, 'login', 'account sign-in', req.ip ?? null);
      audit.record(user.id, 'login', 'account_access', { username: user.username });
      setSessionCookie(res, token);
      res.json({
        ok: true,
        user: { id: user.id, username: user.username, email: user.email, role: user.role },
      });
    } catch (err) {
      next(err);
    }
  });

  router.post('/logout', requireAuth, (req, res, next) => {
    try {
      const token = req.user.sessionToken;
      deleteSession.run(token);
      insertAccess.run(req.user.id, 'logout', 'account sign-out', req.ip ?? null);
      audit.record(req.user.id, 'logout', 'account_access', { username: req.user.username });
      clearSessionCookie(res);
      res.json({ ok: true });
    } catch (err) {
      next(err);
    }
  });

  router.get('/me', requireAuth, (req, res) => {
    res.json({
      ok: true,
      user: {
        id: req.user.id,
        username: req.user.username,
        email: req.user.email,
        role: req.user.role,
        status: req.user.status,
      },
    });
  });

  void getSession;
  return router;
}
