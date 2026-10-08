import { getDb } from '../db/index.js';
import { hashPassword, verifyPassword } from '../lib/crypto.js';
import { apiError, ok, publicUser } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';
import {
  createSession,
  clearSessionCookie,
  revokeSession,
  setSessionCookie
} from '../middleware/auth.js';
import env from '../config/env.js';

function accountAccessLog(db, { userId, action, details, ip }) {
  db.prepare(`INSERT INTO account_access (user_id, action, details, ip) VALUES (?, ?, ?, ?)`)
    .run(userId, action, details || null, ip || null);
}

function userByUsernameOrEmail(db, identifier) {
  return db.prepare(`SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? OR u.email = ?`)
    .get(identifier, identifier);
}

export async function register(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      username: { type: 'string', required: true, minLength: 3, maxLength: 32, pattern: /^[a-zA-Z0-9_.-]+$/, label: 'Username', message: 'Username may only contain letters, numbers, dots, dashes and underscores.' },
      email: { type: 'string', required: true, email: true, maxLength: 120, label: 'Email' },
      password: { type: 'string', required: true, minLength: 6, maxLength: 128, label: 'Password' },
      displayName: { type: 'string', maxLength: 80, label: 'Display name' }
    });
    if (hasErrors(errors)) {
      return next(apiError(400, 'VALIDATION_ERROR', 'Please correct the marked fields.', ));
    }

    const duplicate = db.prepare('SELECT id, username, email FROM users WHERE username = ? OR email = ?').get(body.username, body.email);
    if (duplicate) {
      const field = duplicate.username === body.username ? 'username' : 'email';
      return next(apiError(409, 'DUPLICATE_ACCOUNT', `An account with this ${field} already exists.`));
    }

    const authorRole = db.prepare("SELECT id FROM roles WHERE name = 'author'").get();
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const userId = db.prepare(`INSERT INTO users (username, email, password_hash, role_id, display_name, status, created_at, updated_at)
      VALUES (@username, @email, @hash, @roleId, @displayName, 'active', @now, @now)`)
      .run({
        username: body.username,
        email: body.email,
        hash: hashPassword(body.password),
        roleId: authorRole ? authorRole.id : 3,
        displayName: body.displayName || body.username,
        now
      }).lastInsertRowid;

    accountAccessLog(db, { userId, action: 'signup', details: 'Account registered', ip: req.ip });
    audit(db, { actorId: userId, action: 'register', entityType: 'users', entityId: userId, details: `New account ${body.username}` });

    const token = createSession(userId, env.sessionTtlDays);
    setSessionCookie(res, token, env.sessionTtlDays);

    const user = db.prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(userId);
    return ok(res, { user: publicUser(user), session: { tokenExpiresInDays: env.sessionTtlDays } }, 'Account created and signed in.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function login(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      identifier: { type: 'string', required: true, maxLength: 160, label: 'Username or email' },
      password: { type: 'string', required: true, maxLength: 128, label: 'Password' }
    });
    if (hasErrors(errors)) {
      return next(apiError(400, 'VALIDATION_ERROR', 'Username or email and password are required.'));
    }

    const user = userByUsernameOrEmail(db, body.identifier);
    if (!user || user.status !== 'active' || !verifyPassword(body.password, user.password_hash)) {
      return next(apiError(401, 'INVALID_CREDENTIALS', 'Invalid credentials or inactive account.'));
    }

    const token = createSession(user.id, env.sessionTtlDays);
    setSessionCookie(res, token, env.sessionTtlDays);
    accountAccessLog(db, { userId: user.id, action: 'signin', details: 'Signed in', ip: req.ip });
    audit(db, { actorId: user.id, action: 'signin', entityType: 'sessions', entityId: user.id, details: 'User signed in' });

    return ok(res, { user: publicUser(user), session: { tokenExpiresInDays: env.sessionTtlDays } }, 'Signed in successfully.');
  } catch (err) {
    return next(err);
  }
}

export async function logout(req, res, next) {
  try {
    if (req.user) {
      const db = getDb();
      accountAccessLog(db, { userId: req.user.id, action: 'signout', details: 'Signed out', ip: req.ip });
      broadcast('auth:signed_out', { username: req.user.username });
    }
    revokeSession(req);
    clearSessionCookie(res);
    return ok(res, { signedOut: true }, 'Signed out successfully.');
  } catch (err) {
    return next(err);
  }
}

export async function me(req, res, next) {
  try {
    if (!req.user) {
      return next(apiError(401, 'AUTH_REQUIRED', 'You must sign in to access this resource.'));
    }
    const db = getDb();
    const user = db.prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?').get(req.user.id);
    return ok(res, { user: publicUser(user) }, 'Current user.');
  } catch (err) {
    return next(err);
  }
}
