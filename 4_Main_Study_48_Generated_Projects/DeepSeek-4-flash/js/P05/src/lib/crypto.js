import crypto from 'node:crypto';

export function hashPassword(password) {
  const salt = crypto.randomBytes(16);
  const derived = crypto.scryptSync(String(password), salt, 64);
  return `scrypt:${salt.toString('hex')}:${derived.toString('hex')}`;
}

export function verifyPassword(password, stored) {
  try {
    const [scheme, saltHex, hashHex] = String(stored).split(':');
    if (scheme !== 'scrypt' || !saltHex || !hashHex) return false;
    const salt = Buffer.from(saltHex, 'hex');
    const expected = Buffer.from(hashHex, 'hex');
    const derived = crypto.scryptSync(String(password), salt, expected.length);
    return derived.length === expected.length && crypto.timingSafeEqual(derived, expected);
  } catch {
    return false;
  }
}

export function randomToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString('hex');
}

export function sha256(value) {
  return crypto.createHash('sha256').update(String(value)).digest('hex');
}

export function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\u00e0-\u00ff]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 90) || 'untitled';
}
