import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, conflict, forbidden, notFound, unauthorized
} from '../errors.js';
import {
  validateEmail, requireString, optionalString
} from '../services/validation.js';
import {
  createSession, revokeSession, setSessionCookie, clearSessionCookie
} from '../services/session.js';
import { recordEvent } from '../services/audit.js';

function hashPassword(password, salt) {
  const useSalt = salt || crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(password, useSalt, 32).toString('hex');
  return { salt: useSalt, hash };
}

function verifyPassword(password, salt, expectedHash) {
  const { hash } = hashPassword(password, salt);
  const a = Buffer.from(hash, 'hex');
  const b = Buffer.from(expectedHash, 'hex');
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function newId() { return 'usr_' + crypto.randomBytes(8).toString('hex'); }

function nowIso() { return new Date().toISOString(); }

function recordAccess(userId, action, outcome, req) {
  const db = getDb();
  db.prepare(`INSERT INTO account_access
    (id, user_id, action, ip_address, user_agent, outcome, created_at)
    VALUES (?,?,?,?,?,?,?)`).run(
      'acc_' + crypto.randomBytes(6).toString('hex'),
      userId, action,
      req.ip || req.headers['x-forwarded-for'] || null,
      req.headers['user-agent'] || null,
      outcome, nowIso()
    );
}

export function registerUser(req, res) {
  const { email, display_name, password, role } = req.body || {};
  if (!validateEmail(email)) throw badRequest('Invalid email');
  const name = requireString(display_name, 'display_name', { min: 1, max: 80 });
  const pwd = requireString(password, 'password', { min: 8, max: 200 });
  const requestedRole = role || 'user';
  if (!['user', 'admin'].includes(requestedRole)) throw badRequest('Role must be user or admin');
  if (requestedRole === 'admin' && (!req.user || req.user.role !== 'admin')) {
    throw forbidden('Only admins may create admin accounts');
  }
  const db = getDb();
  const existing = db.prepare(`SELECT id FROM users WHERE email = ?`).get(email.toLowerCase());
  if (existing) throw conflict('Email already registered');
  const { salt, hash } = hashPassword(pwd);
  const id = newId();
  db.prepare(`INSERT INTO users
    (id, email, display_name, password_hash, password_salt, role, status, created_at, updated_at)
    VALUES (?,?,?,?,?,?, 'active', ?, ?)`).run(
      id, email.toLowerCase(), name, hash, salt, requestedRole, nowIso(), nowIso()
  );
  recordAccess(id, 'register', 'success', req);
  recordEvent({ actorId: id, actorRole: requestedRole, action: 'account.register',
    targetKind: 'user', targetId: id, outcome: 'success' });
  const session = createSession(id);
  setSessionCookie(res, session.id);
  return res.status(201).json({
    ok: true,
    data: {
      user: { id, email: email.toLowerCase(), display_name: name, role: requestedRole, status: 'active' },
      session_id: session.id
    }
  });
}

export function signIn(req, res) {
  const { email, password } = req.body || {};
  if (!validateEmail(email)) throw badRequest('Invalid email');
  const pwd = requireString(password, 'password', { min: 1, max: 200 });
  const db = getDb();
  const user = db.prepare(`SELECT * FROM users WHERE email = ?`).get(email.toLowerCase());
  if (!user || !verifyPassword(pwd, user.password_salt, user.password_hash)) {
    recordAccess(user ? user.id : null, 'sign_in', 'failure', req);
    throw unauthorized('Invalid credentials');
  }
  if (user.status !== 'active') {
    recordAccess(user.id, 'sign_in', 'failure', req);
    throw forbidden('Account is not active');
  }
  const session = createSession(user.id);
  recordAccess(user.id, 'sign_in', 'success', req);
  recordEvent({ actorId: user.id, actorRole: user.role, action: 'account.sign_in',
    targetKind: 'session', targetId: session.id, outcome: 'success' });
  setSessionCookie(res, session.id);
  return res.status(200).json({
    ok: true,
    data: {
      user: { id: user.id, email: user.email, display_name: user.display_name, role: user.role, status: user.status },
      session_id: session.id
    }
  });
}

export function signOut(req, res) {
  if (!req.user || !req.sessionId) {
    clearSessionCookie(res);
    return res.status(200).json({ ok: true, data: { signed_out: true } });
  }
  revokeSession(req.sessionId);
  recordAccess(req.user.id, 'sign_out', 'success', req);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role, action: 'account.sign_out',
    targetKind: 'session', targetId: req.sessionId, outcome: 'success' });
  clearSessionCookie(res);
  return res.status(200).json({ ok: true, data: { signed_out: true } });
}

export function resetPassword(req, res) {
  const { email, new_password } = req.body || {};
  if (!validateEmail(email)) throw badRequest('Invalid email');
  const np = requireString(new_password, 'new_password', { min: 8, max: 200 });
  const db = getDb();
  const user = db.prepare(`SELECT * FROM users WHERE email = ?`).get(email.toLowerCase());
  if (!user) {
    recordAccess(null, 'reset', 'failure', req);
    throw notFound('Account not found');
  }
  const { salt, hash } = hashPassword(np);
  db.prepare(`UPDATE users SET password_hash = ?, password_salt = ?, updated_at = ? WHERE id = ?`)
    .run(hash, salt, nowIso(), user.id);
  recordAccess(user.id, 'reset', 'success', req);
  recordEvent({ actorId: user.id, actorRole: user.role, action: 'account.reset',
    targetKind: 'user', targetId: user.id, outcome: 'success' });
  return res.status(200).json({ ok: true, data: { reset: true } });
}

export function getAccountAccess(req, res) {
  // AI-01: GET /api/ai/account_access — returns account access records for caller.
  if (!req.user) throw unauthorized();
  const db = getDb();
  const records = db.prepare(`
    SELECT id, action, outcome, ip_address, user_agent, created_at
    FROM account_access WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 100
  `).all(req.user.id);
  res.status(200).json({ ok: true, data: { user: req.user, records } });
}

export function patchAccountAccess(req, res) {
  // AI-01: PATCH /api/ai/account_access/{id} — admin updates a user record.
  if (!req.user || req.user.role !== 'admin') throw forbidden();
  const targetId = req.params.id;
  const { display_name, status, role } = req.body || {};
  const db = getDb();
  const target = db.prepare(`SELECT * FROM users WHERE id = ?`).get(targetId);
  if (!target) throw notFound('User not found');
  const updates = [];
  const params = [];
  if (display_name !== undefined) {
    const name = requireString(display_name, 'display_name', { min: 1, max: 80 });
    updates.push('display_name = ?'); params.push(name);
  }
  if (status !== undefined) {
    if (!['active', 'suspended', 'disabled'].includes(status)) throw badRequest('Invalid status');
    updates.push('status = ?'); params.push(status);
  }
  if (role !== undefined) {
    if (!['user', 'admin'].includes(role)) throw badRequest('Invalid role');
    updates.push('role = ?'); params.push(role);
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(targetId);
  db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role, action: 'account.update',
    targetKind: 'user', targetId, outcome: 'success', details: { display_name, status, role } });
  const refreshed = db.prepare(`SELECT id, email, display_name, role, status FROM users WHERE id = ?`).get(targetId);
  res.status(200).json({ ok: true, data: refreshed });
}

export function postAccountAccess(req, res) {
  // AI-01: POST /api/ai/account_access — explicit sign-in endpoint (mirrors
  // the use-case contract while keeping /auth/sign-in for convenience).
  return signIn(req, res);
}
