// Deterministic application errors with stable codes and HTTP status.
export class AppError extends Error {
  constructor(code, message, status = 400, details = undefined) {
    super(message);
    this.name = 'AppError';
    this.code = code;
    this.status = status;
    this.details = details;
  }
  toJSON() {
    return {
      ok: false,
      error: { code: this.code, message: this.message, details: this.details }
    };
  }
}

export const ErrorCodes = Object.freeze({
  VALIDATION: 'validation_error',
  UNAUTHORIZED: 'unauthorized',
  FORBIDDEN: 'forbidden',
  NOT_FOUND: 'not_found',
  CONFLICT: 'conflict',
  UPLOAD_FAILED: 'upload_failed',
  INTERNAL: 'internal_error'
});

export function badRequest(message, details) {
  return new AppError(ErrorCodes.VALIDATION, message, 400, details);
}
export function unauthorized(message = 'Authentication required') {
  return new AppError(ErrorCodes.UNAUTHORIZED, message, 401);
}
export function forbidden(message = 'Forbidden') {
  return new AppError(ErrorCodes.FORBIDDEN, message, 403);
}
export function notFound(message = 'Not found') {
  return new AppError(ErrorCodes.NOT_FOUND, message, 404);
}
export function conflict(message, details) {
  return new AppError(ErrorCodes.CONFLICT, message, 409, details);
}
export function uploadFailed(message, details) {
  return new AppError(ErrorCodes.UPLOAD_FAILED, message, 400, details);
}
