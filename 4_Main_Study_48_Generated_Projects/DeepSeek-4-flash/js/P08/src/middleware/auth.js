import { config } from '../config.js';
import { getDb } from '../db/database.js';
import { unauthorized, forbidden } from '../lib/errors.js';

function parseCookies(req) {
  const header = req.headers.cookie || '';
  const out = {};
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    const key = part.slice(0, idx).trim();
    const value = decodeURIComponent(part.slice(idx + 1).trim());
    out[key] = value;
  }
  return out;
}

function sessionFromRequest(req) {
  const db = getDb();
  const cookies = parseCookies(req);
  const token = cookies[config.sessionCookieName];
  if (!token) return { session: null, user: null };
  const session = db.prepare('SELECT * FROM sessions WHERE id = ?').get(token);
  if (!session) return { session: null, user: null };
  const expiresMs = new Date(String(session.expires_at).replace(' ', 'T') + 'Z').getTime();
  if (!Number.isFinite(expiresMs) || expiresMs < Date.now()) {
    db.prepare('DELETE FROM sessions WHERE id = ?').run(token);
    return { session: null, user: null };
  }
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(session.user_id);
  if (!user || !user.active) return { session: null, user: null };
  return { session, user };
}

export function loadSession(req, res, next) {
  const { session, user } = sessionFromRequest(req);
  req.session = session;
  req.user = user;
  next();
}

export function requireAuth(req, res, next) {
  if (!req.user) return next(unauthorized('Please sign in to continue'));
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) return next(unauthorized('Please sign in to continue'));
    if (!roles.includes(req.user.role)) {
      return next(forbidden('Your role is not allowed to perform this action'));
    }
    next();
  };
}
