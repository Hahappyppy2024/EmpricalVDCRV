export function validateEmail(email) {
  if (typeof email !== 'string') return false;
  if (email.length < 3 || email.length > 254) return false;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function requireString(value, fieldName, { min = 1, max = 10000 } = {}) {
  if (typeof value !== 'string') {
    const e = new Error(`${fieldName} must be a string`);
    e.name = 'ValidationError';
    throw e;
  }
  const trimmed = value.trim();
  if (trimmed.length < min) {
    const e = new Error(`${fieldName} must be at least ${min} characters`);
    e.name = 'ValidationError';
    throw e;
  }
  if (trimmed.length > max) {
    const e = new Error(`${fieldName} must be at most ${max} characters`);
    e.name = 'ValidationError';
    throw e;
  }
  return trimmed;
}

export function optionalString(value, fieldName, opts = {}) {
  if (value === undefined || value === null || value === '') return null;
  return requireString(value, fieldName, opts);
}

export function requireEnum(value, fieldName, allowed) {
  if (!allowed.includes(value)) {
    const e = new Error(`${fieldName} must be one of: ${allowed.join(', ')}`);
    e.name = 'ValidationError';
    e.details = { allowed, received: value };
    throw e;
  }
  return value;
}

export function optionalEnum(value, fieldName, allowed) {
  if (value === undefined || value === null || value === '') return undefined;
  return requireEnum(value, fieldName, allowed);
}

export function requireNumber(value, fieldName, { min = -Infinity, max = Infinity, integer = false } = {}) {
  const n = Number(value);
  if (!Number.isFinite(n)) {
    const e = new Error(`${fieldName} must be a number`);
    e.name = 'ValidationError';
    throw e;
  }
  if (integer && !Number.isInteger(n)) {
    const e = new Error(`${fieldName} must be an integer`);
    e.name = 'ValidationError';
    throw e;
  }
  if (n < min || n > max) {
    const e = new Error(`${fieldName} must be between ${min} and ${max}`);
    e.name = 'ValidationError';
    throw e;
  }
  return n;
}

export function optionalNumber(value, fieldName, opts) {
  if (value === undefined || value === null || value === '') return undefined;
  return requireNumber(value, fieldName, opts);
}
