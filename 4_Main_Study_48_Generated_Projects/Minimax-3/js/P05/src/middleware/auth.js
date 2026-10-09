import { COOKIE_NAME, getSession } from '../services/session.js';

export function attachSession(req, _res, next) {
  const sid = req.cookies?.[COOKIE_NAME];
  const ctx = getSession(sid);
  req.session = ctx?.session || null;
  req.user = ctx?.user || null;
  req.sid = sid || null;
  next();
}

export function requireAuth(req, res, next) {
  if (!req.user) {
    if (req.accepts('html')) {
      return res.redirect('/login');
    }
    return res.status(401).json({ error: 'auth_required', message: 'Authentication required' });
  }
  next();
}

export function requireRole(...allowed) {
  return (req, res, next) => {
    if (!req.user) {
      if (req.accepts('html')) return res.redirect('/login');
      return res.status(401).json({ error: 'auth_required' });
    }
    if (!allowed.includes(req.user.role)) {
      return res.status(403).json({ error: 'forbidden', message: 'Insufficient role' });
    }
    next();
  };
}
