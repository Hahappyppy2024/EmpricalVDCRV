import crypto from 'node:crypto';
export function hashPassword(password,salt=crypto.randomBytes(16).toString('hex')){return `${salt}:${crypto.scryptSync(password,salt,32).toString('hex')}`;}
export function verifyPassword(password,stored){const [salt,digest]=stored.split(':');const actual=crypto.scryptSync(password,salt,32);return crypto.timingSafeEqual(actual,Buffer.from(digest,'hex'));}
export const tokenHash=token=>crypto.createHash('sha256').update(token).digest('hex');
export const randomToken=()=>crypto.randomBytes(24).toString('base64url');
