import { HttpError } from './error.js';

export function sessionFromDb(db) {
  return function resolveSession(req, res, next) {
    const token = req.cookies?.[process.env.SESSION_COOKIE_NAME ?? 'aiwebui_sid'] ?? req.headers['x-session-token'];
    if (!token) {
      req.user = null;
      return next();
    }
    const row = db
      .prepare(
        `SELECT s.token, s.expires_at, u.id, u.username, u.email, u.role, u.status
         FROM sessions s JOIN users u ON u.id = s.user_id
         WHERE s.token = ?`
      )
      .get(token);
    if (!row || (row.expires_at && new Date(row.expires_at) < new Date()) || row.status === 'suspended') {
      req.user = null;
      return next();
    }
    req.user = {
      id: row.id,
      username: row.username,
      email: row.email,
      role: row.role,
      status: row.status,
      sessionToken: row.token,
    };
    next();
  };
}

export function requireAuth(req, res, next) {
  if (!req.user) {
    return next(new HttpError(401, 'Authentication required', 'AUTH_REQUIRED'));
  }
  next();
}

export function requireAdmin(req, res, next) {
  if (!req.user) {
    return next(new HttpError(401, 'Authentication required', 'AUTH_REQUIRED'));
  }
  if (req.user.role !== 'admin') {
    return next(new HttpError(403, 'Administrator access required', 'ADMIN_REQUIRED'));
  }
  next();
}
