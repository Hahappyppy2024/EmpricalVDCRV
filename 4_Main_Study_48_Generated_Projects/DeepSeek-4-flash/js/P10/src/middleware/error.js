export class HttpError extends Error {
  constructor(status, message, code = null) {
    super(message);
    this.status = status;
    this.code = code ?? `HTTP_${status}`;
  }
}

export function notFound(req, res, next) {
  next(new HttpError(404, 'Not found'));
}

export function errorHandler(err, req, res, next) {
  if (res.headersSent) {
    return next(err);
  }
  const status = err instanceof HttpError ? err.status : 500;
  const message = err instanceof HttpError ? err.message : 'Internal server error';
  if (status >= 500) {
    console.error('[server]', err);
  }
  res.status(status).json({
    ok: false,
    error: { code: err.code ?? `HTTP_${status}`, message },
  });
}
