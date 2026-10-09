export class AppError extends Error {
  constructor(message, status = 400, code = 'invalid_request') {
    super(message);
    this.status = status;
    this.code = code;
  }
}

export function asyncHandler(fn) {
  return (req, res, next) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
}

export function ok(res, data, status = 200) {
  return res.status(status).json({ ok: true, data });
}

export function fail(res, message, status = 400, code = 'invalid_request', details) {
  const body = { ok: false, error: { code, message } };
  if (details) body.error.details = details;
  return res.status(status).json(body);
}

export function requireAuth(req, res, next) {
  if (!req.session || !req.session.user) {
    return fail(res, 'Authentication required', 401, 'unauthenticated');
  }
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.session || !req.session.user) {
      return fail(res, 'Authentication required', 401, 'unauthenticated');
    }
    if (!roles.includes(req.session.user.role)) {
      return fail(res, `Role ${req.session.user.role} not permitted`, 403, 'forbidden');
    }
    next();
  };
}
