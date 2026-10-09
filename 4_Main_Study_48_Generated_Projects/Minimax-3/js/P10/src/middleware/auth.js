import { readSessionCookie, loadSessionUser } from '../services/session.js';
import { unauthorized, forbidden } from '../errors.js';

export function attachSession(req, _res, next) {
  const sid = readSessionCookie(req);
  const session = loadSessionUser(sid);
  req.sessionId = session?.sid || null;
  req.user = session?.user || null;
  next();
}

export function requireAuth(req, _res, next) {
  if (!req.user) return next(unauthorized());
  next();
}

export function requireRole(role) {
  return (req, _res, next) => {
    if (!req.user) return next(unauthorized());
    if (req.user.role !== role) return next(forbidden(`Requires role ${role}`));
    next();
  };
}
