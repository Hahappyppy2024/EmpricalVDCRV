import { findSession } from '../services/session.js';
import { env } from '../config.js';

export function loadAuth(req, res, next) {
  const cfg = env();
  const cookie = req.cookies ? req.cookies[cfg.SESSION_COOKIE_NAME] : null;
  if (cookie) {
    const s = findSession(cookie);
    if (s) req.session = s;
  }
  next();
}

export function requireAuth(req, res, next) {
  if (!req.session) return res.status(401).json({ error: 'unauthorized', message: 'Sign in required' });
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.session) return res.status(401).json({ error: 'unauthorized', message: 'Sign in required' });
    if (!roles.includes(req.session.user.role)) {
      return res.status(403).json({ error: 'forbidden', message: `Requires role: ${roles.join(',')}` });
    }
    next();
  };
}