import { AppError } from './errors.js';

export function parseCookies(header) {
  const out = {};
  if (!header) return out;
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    const key = part.slice(0, idx).trim();
    const value = part.slice(idx + 1).trim();
    if (key) out[key] = decodeURIComponent(value);
  }
  return out;
}

export function setSessionCookie(res, token, maxAgeSeconds) {
  res.setHeader('Set-Cookie', `sid=${encodeURIComponent(token)}; HttpOnly; Path=/; Max-Age=${maxAgeSeconds}; SameSite=Lax`);
}

export function clearSessionCookie(res) {
  res.setHeader('Set-Cookie', 'sid=; HttpOnly; Path=/; Max-Age=0; SameSite=Lax');
}

export function requireFields(body, fields) {
  const missing = [];
  const invalid = [];
  for (const field of fields) {
    const value = body[field];
    if (value === undefined || value === null || value === '') {
      missing.push(field);
    }
  }
  if (missing.length > 0) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Missing required fields.', { missing });
  }
  if (invalid.length > 0) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Invalid field values.', { invalid });
  }
}

export function isIsoDate(value) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value));
}

export function isValidEmail(value) {
  return typeof value === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}
