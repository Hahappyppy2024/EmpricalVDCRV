import crypto from 'node:crypto';

// Deterministic salted SHA-256 for seed data and runtime
// For seed users we use a deterministic hash so the same login works every reset.
export function hashPassword(password, salt = 'cms_static_seed_salt') {
  return crypto.createHash('sha256').update(`${salt}:${password}`).digest('hex');
}

export function verifyPassword(password, hash) {
  const computed = hashPassword(password);
  return crypto.timingSafeEqual(Buffer.from(computed, 'hex'), Buffer.from(hash, 'hex'));
}
