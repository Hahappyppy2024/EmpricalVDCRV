import crypto from 'node:crypto';
export const token=()=>crypto.randomBytes(24).toString('hex');
export const hashToken=value=>crypto.createHash('sha256').update(value).digest('hex');
export function hashPassword(password,salt=crypto.randomBytes(16).toString('hex')){return `${salt}:${crypto.scryptSync(password,salt,32).toString('hex')}`;}
export function verifyPassword(password,stored){const [salt,expected]=stored.split(':');const actual=crypto.scryptSync(password,salt,32);return expected?.length===64&&crypto.timingSafeEqual(actual,Buffer.from(expected,'hex'));}
export const escapeHtml=value=>String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
