import { getDb } from '../db/connection.js';
import { hashPassword, verifyPassword } from '../services/crypto.js';
import { createSession, destroySession } from '../services/session.js';
import { validateRegister, validateLogin, validateRecovery } from '../services/validate.js';
import { record } from '../services/audit.js';
import { env } from '../config.js';
import { asyncRoute, sendJson } from '../services/http.js';

const cfg = env();

export function registerAccountRoutes(app) {
  app.get('/api/hotel/account_access', asyncRoute(async (req, res) => {
    if (req.session) {
      return sendJson(res, 200, { signed_in: true, user: req.session.user });
    }
    sendJson(res, 200, { signed_in: false });
  }));

  app.post('/api/hotel/account_access', asyncRoute(async (req, res) => {
    const body = req.body || {};
    const mode = body.mode || 'register';
    const db = getDb();

    if (mode === 'register') {
      const errs = validateRegister(body);
      if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
      const existing = db.prepare('SELECT id FROM users WHERE email = ?').get(body.email);
      if (existing) return sendJson(res, 409, { error: 'conflict', message: 'Email already registered' });
      const info = db.prepare(`INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, 'guest')`)
        .run(body.email, hashPassword(body.password), body.display_name);
      const user = { id: info.lastInsertRowid, email: body.email, display_name: body.display_name, role: 'guest' };
      const s = createSession(user.id);
      res.setHeader('set-cookie', cookieHeader(s.token));
      record({ actor: user, action: 'account.register', targetType: 'user', targetId: user.id });
      return sendJson(res, 201, { user });
    }

    if (mode === 'login') {
      const errs = validateLogin(body);
      if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
      const row = db.prepare('SELECT * FROM users WHERE email = ?').get(body.email);
      if (!row || !verifyPassword(body.password, row.password_hash)) {
        return sendJson(res, 401, { error: 'unauthorized', message: 'Invalid credentials' });
      }
      const user = { id: row.id, email: row.email, display_name: row.display_name, role: row.role };
      const s = createSession(user.id);
      res.setHeader('set-cookie', cookieHeader(s.token));
      record({ actor: user, action: 'account.login', targetType: 'user', targetId: user.id });
      return sendJson(res, 200, { user });
    }

    if (mode === 'recovery') {
      const errs = validateRecovery(body);
      if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
      const row = db.prepare('SELECT id, email, display_name FROM users WHERE email = ?').get(body.email);
      record({ actor: row ? { id: row.id, role: 'guest' } : null, action: 'account.recovery_request', targetType: 'user', targetId: row ? row.id : null, detail: 'simulated email sent' });
      return sendJson(res, 200, { message: 'If the email exists, a recovery link has been simulated.' });
    }

    if (mode === 'logout') {
      const token = req.session ? req.session.token : null;
      destroySession(token);
      res.setHeader('set-cookie', cookieHeader('', true));
      return sendJson(res, 200, { message: 'Signed out' });
    }

    sendJson(res, 400, { error: 'validation_error', message: 'Unknown mode' });
  }));

  app.patch('/api/hotel/account_access/:id', asyncRoute(async (req, res) => {
    if (!req.session) return sendJson(res, 401, { error: 'unauthorized' });
    const id = Number(req.params.id);
    if (req.session.user.id !== id && req.session.user.role !== 'admin') {
      return sendJson(res, 403, { error: 'forbidden', message: 'Cannot update other user' });
    }
    const body = req.body || {};
    const updates = [];
    const params = [];
    if (body.display_name) { updates.push('display_name = ?'); params.push(body.display_name); }
    if (body.password) { updates.push('password_hash = ?'); params.push(hashPassword(body.password)); }
    if (!updates.length) return sendJson(res, 400, { error: 'validation_error', message: 'No updatable fields' });
    params.push(id);
    getDb().prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...params);
    record({ actor: req.session.user, action: 'account.update', targetType: 'user', targetId: id });
    sendJson(res, 200, { id, updated: true });
  }));
}

function cookieHeader(token, clear = false) {
  const parts = [`${cfg.SESSION_COOKIE_NAME}=${clear ? '' : token}`];
  if (clear) parts.push('Max-Age=0');
  parts.push('Path=/');
  parts.push('HttpOnly');
  parts.push('SameSite=Lax');
  return parts.join('; ');
}