export class AppError extends Error {
  constructor(statusCode, code, message, details) {
    super(message);
    this.statusCode = statusCode;
    this.code = code;
    this.details = details;
    this.isOperational = true;
  }
}

export function notFoundHandler(req, res) {
  const message = 'The requested resource was not found.';
  if (req.path.startsWith('/api')) {
    return res.status(404).json({ error: { code: 'NOT_FOUND', message } });
  }
  return res.status(404).render('error', {
    statusCode: 404,
    code: 'NOT_FOUND',
    message,
    title: 'Not found',
    page: 'error',
    user: req.user,
    isAuthenticated: req.isAuthenticated,
  });
}

// eslint-disable-next-line no-unused-vars
export function errorHandler(err, req, res, next) {
  const statusCode = err.statusCode || 500;
  const code = err.code || 'INTERNAL_ERROR';
  const message = err.isOperational
    ? err.message
    : 'An unexpected error occurred. Please try again.';

  if (statusCode >= 500) {
    // eslint-disable-next-line no-console
    console.error('[error]', err.stack || err.message);
  }

  if (req.path.startsWith('/api') || req.headers.accept?.includes('application/json')) {
    const body = { error: { code, message } };
    if (err.details) body.error.details = err.details;
    return res.status(statusCode).json(body);
  }

  return res.status(statusCode).render('error', {
    statusCode,
    code,
    message,
    title: 'Error',
    page: 'error',
    user: req.user,
    isAuthenticated: req.isAuthenticated,
  });
}
