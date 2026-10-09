export class AppError extends Error {
  constructor(message, status = 400, code = 'app_error') {
    super(message);
    this.status = status;
    this.code = code;
  }
}

export function badRequest(message, details = []) {
  return new AppError(message, 400, 'validation_error');
}

export function unauthorized(message = 'Authentication required') {
  return new AppError(message, 401, 'unauthorized');
}

export function forbidden(message = 'Access denied') {
  return new AppError(message, 403, 'forbidden');
}

export function notFound(message = 'Record not found') {
  return new AppError(message, 404, 'not_found');
}

export function conflict(message) {
  return new AppError(message, 409, 'conflict');
}