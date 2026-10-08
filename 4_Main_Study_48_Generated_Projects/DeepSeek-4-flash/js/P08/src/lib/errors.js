export class AppError extends Error {
  constructor(status, code, message, details) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export function badRequest(message = 'Invalid request', details) {
  return new AppError(400, 'VALIDATION_ERROR', message, details);
}

export function unauthorized(message = 'Authentication required') {
  return new AppError(401, 'UNAUTHENTICATED', message);
}

export function forbidden(message = 'You are not allowed to perform this action') {
  return new AppError(403, 'UNAUTHORIZED', message);
}

export function notFound(message = 'Record not found') {
  return new AppError(404, 'NOT_FOUND', message);
}

export function conflict(message = 'State conflict', details) {
  return new AppError(409, 'STATE_CONFLICT', message, details);
}

export function assert(condition, error) {
  if (!condition) throw error;
}
