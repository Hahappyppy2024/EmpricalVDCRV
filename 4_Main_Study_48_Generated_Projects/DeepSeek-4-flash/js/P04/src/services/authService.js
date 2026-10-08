import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { createSession, revokeSessionByToken } from './sessionService.js';
import { nowSql } from './sessionService.js';

const SESSION_DAYS = 30;

function expiresSqlForReset() {
  return new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
}

function logAccess(userId, action, details) {
  getDb().prepare('INSERT INTO account_access_log (user_id, action, details) VALUES (?, ?, ?)')
    .run(userId, action, details);
}

function recordNotification(recipient, subject, body) {
  getDb().prepare('INSERT INTO outbound_notifications (recipient, subject, body) VALUES (?, ?, ?)')
    .run(recipient, subject, body);
}

export function register({ name, email, password, phone }) {
  const db = getDb();
  const existing = db.prepare('SELECT id FROM users WHERE email = ?').get(email.toLowerCase().trim());
  if (existing) {
    throw new AppError(409, 'EMAIL_TAKEN', 'An account with this email already exists.');
  }
  const passwordHash = bcrypt.hashSync(password, 10);
  const info = db.prepare(`
    INSERT INTO users (email, password_hash, name, role, phone)
    VALUES (?, ?, ?, 'guest', ?)
  `).run(email.toLowerCase().trim(), passwordHash, name.trim(), phone || null);

  const userId = info.lastInsertRowid;
  const token = createSession(userId);
  logAccess(userId, 'register', 'Guest account created');
  recordNotification(email, 'Welcome to Meridian Grand Hotel', 'Your account is ready. You can now search rooms and manage bookings.');

  const user = db.prepare('SELECT id, email, name, role, phone, created_at FROM users WHERE id = ?').get(userId);
  return { token, user };
}

export function login({ email, password }) {
  const db = getDb();
  const user = db.prepare('SELECT * FROM users WHERE email = ?').get(email.toLowerCase().trim());
  if (!user || !bcrypt.compareSync(password, user.password_hash)) {
    throw new AppError(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.');
  }
  const token = createSession(user.id);
  logAccess(user.id, 'login', 'Sign-in succeeded');
  return {
    token,
    user: { id: user.id, email: user.email, name: user.name, role: user.role, phone: user.phone, created_at: user.created_at },
  };
}

export function logout(token) {
  if (token) revokeSessionByToken(token);
}

export function requestPasswordReset({ email }) {
  const db = getDb();
  const user = db.prepare('SELECT id, email, name FROM users WHERE email = ?').get(email.toLowerCase().trim());
  if (!user) {
    throw new AppError(404, 'USER_NOT_FOUND', 'No account is associated with that email.');
  }
  const token = crypto.randomBytes(32).toString('hex');
  db.prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
    .run(user.id, token, expiresSqlForReset());
  const resetUrl = `${process.env.PUBLIC_BASE_URL || 'http://localhost:3000'}/account/reset?token=${token}`;
  recordNotification(user.email, 'Password reset requested', `Use this link to reset your password (valid 60 minutes): ${resetUrl}`);
  logAccess(user.id, 'reset_request', 'Password reset link dispatched via deterministic email adapter');
  return { email: user.email, resetUrl };
}

export function confirmPasswordReset({ token, password }) {
  const db = getDb();
  const row = db.prepare(`
    SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token = ?
  `).get(token);
  if (!row || row.used_at) {
    throw new AppError(400, 'INVALID_RESET_TOKEN', 'This reset link is invalid or has already been used.');
  }
  if (new Date(row.expires_at + 'Z') <= new Date()) {
    throw new AppError(400, 'RESET_TOKEN_EXPIRED', 'This reset link has expired. Request a new one.');
  }
  const passwordHash = bcrypt.hashSync(password, 10);
  db.prepare('UPDATE users SET password_hash = ? WHERE id = ?').run(passwordHash, row.user_id);
  db.prepare('UPDATE password_resets SET used_at = ? WHERE id = ?').run(nowSql(), row.id);
  db.prepare('UPDATE sessions SET revoked_at = ? WHERE user_id = ?').run(nowSql(), row.user_id);
  logAccess(row.user_id, 'reset_confirm', 'Password reset completed');
  return { ok: true };
}

export function publicUser(row) {
  if (!row) return null;
  return { id: row.id, email: row.email, name: row.name, role: row.role, phone: row.phone, created_at: row.created_at };
}

export { SESSION_DAYS };
