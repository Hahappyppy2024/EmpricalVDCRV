export function validationError(message, fields = []) {
  const err = new Error(message);
  err.status = 400;
  err.code = 'validation_error';
  err.fields = fields;
  return err;
}

export function authError(message = 'Authentication required') {
  const err = new Error(message);
  err.status = 401;
  err.code = 'auth_required';
  return err;
}

export function forbidError(message = 'Forbidden') {
  const err = new Error(message);
  err.status = 403;
  err.code = 'forbidden';
  return err;
}

export function notFoundError(message = 'Not found') {
  const err = new Error(message);
  err.status = 404;
  err.code = 'not_found';
  return err;
}

export function conflictError(message) {
  const err = new Error(message);
  err.status = 409;
  err.code = 'conflict';
  return err;
}

export function serverError(message = 'Internal server error') {
  const err = new Error(message);
  err.status = 500;
  err.code = 'server_error';
  return err;
}
