import crypto from 'node:crypto';

const SCRYPT_KEYLEN = 64;

export function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(password, salt, SCRYPT_KEYLEN).toString('hex');
  return `${salt}:${hash}`;
}

export function verifyPassword(password, stored) {
  const [salt, expected] = String(stored || '').split(':');
  if (!salt || !expected) return false;
  const actual = crypto.scryptSync(password, salt, SCRYPT_KEYLEN);
  const expectedBuf = Buffer.from(expected, 'hex');
  if (actual.length !== expectedBuf.length) return false;
  return crypto.timingSafeEqual(actual, expectedBuf);
}

export function randomToken(bytes = 24) {
  return crypto.randomBytes(bytes).toString('hex');
}

export function uuid() {
  return crypto.randomUUID();
}
