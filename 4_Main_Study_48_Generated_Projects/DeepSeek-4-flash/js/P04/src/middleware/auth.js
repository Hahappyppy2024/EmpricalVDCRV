import { parseCookies } from './validation.js';
import { AppError } from './errors.js';
import { getSessionByToken, isSessionActive } from '../services/sessionService.js';

export function loadSession(req, res, next) {
  const cookies = parseCookies(req.headers.cookie);
  const token = cookies.sid;
  if (token) {
    const session = getSessionByToken(token);
    if (session && isSessionActive(session)) {
      req.session = session;
      req.user = {
        id: session.id,
        email: session.email,
        name: session.name,
        role: session.role,
        phone: session.phone,
        member_since: session.member_since,
        session_id: session.session_id,
      };
    }
  }
  req.isAuthenticated = Boolean(req.user);
  next();
}

export function requireAuth(req, res, next) {
  if (!req.isAuthenticated) {
    throw new AppError(401, 'UNAUTHORIZED', 'You must be signed in to perform this action.');
  }
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.isAuthenticated) {
      throw new AppError(401, 'UNAUTHORIZED', 'You must be signed in to perform this action.');
    }
    if (!roles.includes(req.user.role)) {
      throw new AppError(403, 'FORBIDDEN', 'You do not have permission to perform this action.');
    }
    next();
  };
}
