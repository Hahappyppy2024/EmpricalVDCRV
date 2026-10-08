import { getDb } from '../db/index.js';
import { randomToken, sha256 } from '../lib/crypto.js';
import { apiError, parseCookies } from '../lib/http.js';

const SESSION_COOKIE = 'cms_session';

export function createSession(userId, ttlDays) {
  const db = getDb();
  const token = randomToken(32);
  const expiresAt = new Date(Date.now() + ttlDays * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
  db.prepare('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)').run(sha256(token), userId, expiresAt);
  return token;
}

export function setSessionCookie(res, token, ttlDays) {
  res.cookie(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: false,
    path: '/',
    maxAge: ttlDays * 24 * 60 * 60 * 1000
  });
}

export function clearSessionCookie(res) {
  res.clearCookie(SESSION_COOKIE, { httpOnly: true, sameSite: 'lax', path: '/' });
}

export function loadSession(req, _res, next) {
  const db = getDb();
  const cookies = parseCookies(req);
  const token = cookies[SESSION_COOKIE];
  req.user = null;
  req.session = null;
  if (!token) return next();

  const session = db.prepare(`
    SELECT s.id AS session_id, s.token_hash, s.expires_at, s.revoked_at, u.*, r.name AS role_name
    FROM sessions s
    JOIN users u ON u.id = s.user_id
    JOIN roles r ON r.id = u.role_id
    WHERE s.token_hash = ?
  `).get(sha256(token));

  if (!session || session.revoked_at || session.expires_at <= new Date().toISOString().slice(0, 19).replace('T', ' ') || session.status !== 'active') {
    return next();
  }

  req.session = session;
  req.user = {
    id: session.id,
    username: session.username,
    email: session.email,
    display_name: session.display_name,
    status: session.status,
    role_id: session.role_id,
    role_name: session.role_name
  };
  return next();
}

export function revokeSession(req) {
  const db = getDb();
  const cookies = parseCookies(req);
  const token = cookies[SESSION_COOKIE];
  if (!token) return false;
  const result = db.prepare('UPDATE sessions SET revoked_at = ? WHERE token_hash = ?')
    .run(new Date().toISOString().slice(0, 19).replace('T', ' '), sha256(token));
  return result.changes > 0;
}

export function requireAuth(req, _res, next) {
  if (!req.user) {
    return next(apiError(401, 'AUTH_REQUIRED', 'You must sign in to access this resource.'));
  }
  return next();
}

export function requireRole(...roles) {
  return (req, _res, next) => {
    if (!req.user) {
      return next(apiError(401, 'AUTH_REQUIRED', 'You must sign in to access this resource.'));
    }
    if (!roles.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'You do not have permission to perform this action.'));
    }
    return next();
  };
}
