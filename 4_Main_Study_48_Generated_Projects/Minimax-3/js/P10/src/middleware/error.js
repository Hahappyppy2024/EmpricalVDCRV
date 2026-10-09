import { AppError } from '../errors.js';

export function notFoundHandler(_req, res) {
  res.status(404).json({
    ok: false,
    error: { code: 'not_found', message: 'Route not found' }
  });
}

export function errorHandler(err, req, res, _next) {
  if (err instanceof AppError) {
    return res.status(err.status).json(err.toJSON());
  }
  // Validation thrown synchronously (e.g., zod-like manual validation)
  if (err && err.name === 'ValidationError') {
    return res.status(400).json({
      ok: false,
      error: { code: 'validation_error', message: err.message, details: err.details }
    });
  }
  console.error('[error]', err);
  res.status(500).json({
    ok: false,
    error: { code: 'internal_error', message: 'Unexpected server error' }
  });
}
