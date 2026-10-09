import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, conflict, forbidden, notFound, unauthorized
} from '../errors.js';
import {
  requireString, optionalString, requireEnum, optionalEnum
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';

function nowIso() { return new Date().toISOString(); }
function hashSecret(secret, salt) {
  const useSalt = salt || crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(secret, useSalt, 32).toString('hex');
  return { salt: useSalt, hash };
}

function maskKey(value) {
  if (typeof value !== 'string' || value.length < 6) return '***';
  return value.slice(0, 6) + '...' + value.slice(-4);
}

function visibleKey(row, includeSecret) {
  if (!row) return null;
  const out = {
    id: row.id, owner_id: row.owner_id, label: row.label, provider: row.provider,
    masked_key: row.masked_key, last_rotated_at: row.last_rotated_at,
    revoked_at: row.revoked_at, created_at: row.created_at
  };
  if (includeSecret) {
    out.can_view_secret = false;
    out.secret_hint = 'Use the rotation endpoint to refresh the key value.';
  }
  return out;
}

export function listKeys(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const where = ['owner_id = ?'];
  const params = [req.user.id];
  if (req.user.role !== 'admin') {
    where.push('revoked_at IS NULL');
  }
  const rows = db.prepare(`
    SELECT id, owner_id, label, provider, masked_key, last_rotated_at,
           revoked_at, created_at
    FROM api_key_management WHERE ${where.join(' AND ')}
    ORDER BY created_at DESC LIMIT 200
  `).all(...params);
  res.json({ ok: true, data: { items: rows.map(r => visibleKey(r, false)), total: rows.length } });
}

export function createKey(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const label = requireString(req.body?.label, 'label', { min: 1, max: 120 });
  const provider = requireString(req.body?.provider, 'provider', { min: 1, max: 80 });
  requireEnum(provider.toLowerCase(), 'provider',
    ['openai-compatible', 'anthropic', 'azure', 'google', 'internal']);
  const secret = requireString(req.body?.secret, 'secret', { min: 8, max: 200 });
  const owner = req.body?.owner_id && req.user.role === 'admin'
    ? req.body.owner_id : req.user.id;
  const exists = db.prepare(`
    SELECT id FROM api_key_management
    WHERE owner_id = ? AND label = ? AND revoked_at IS NULL
  `).get(owner, label);
  if (exists) throw conflict('A key with this label already exists for the owner');
  const { salt, hash } = hashSecret(secret);
  const id = 'key_' + crypto.randomBytes(8).toString('hex');
  db.prepare(`INSERT INTO api_key_management
    (id, owner_id, label, provider, masked_key, secret_hash, secret_salt,
     last_rotated_at, created_at)
    VALUES (?,?,?,?,?,?,?,?,?)`).run(
      id, owner, label, provider.toLowerCase(), maskKey(secret), hash, salt, nowIso(), nowIso()
    );
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'api_key.create', targetKind: 'api_key', targetId: id, outcome: 'success',
    details: { provider, owner_id: owner } });
  const stored = db.prepare(`SELECT * FROM api_key_management WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: visibleKey(stored, false) });
}

export function rotateKey(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const key = db.prepare(`SELECT * FROM api_key_management WHERE id = ?`).get(req.params.id);
  if (!key) throw notFound('API key not found');
  if (key.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  if (key.revoked_at) throw badRequest('Cannot rotate a revoked key');
  const newSecret = requireString(req.body?.secret, 'secret', { min: 8, max: 200 });
  const { salt, hash } = hashSecret(newSecret);
  db.prepare(`UPDATE api_key_management
    SET masked_key = ?, secret_hash = ?, secret_salt = ?, last_rotated_at = ?
    WHERE id = ?`).run(maskKey(newSecret), hash, salt, nowIso(), req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'api_key.rotate', targetKind: 'api_key', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM api_key_management WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: visibleKey(refreshed, false) });
}

export function revokeKey(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const key = db.prepare(`SELECT * FROM api_key_management WHERE id = ?`).get(req.params.id);
  if (!key) throw notFound('API key not found');
  if (key.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  if (key.revoked_at) return res.json({ ok: true, data: visibleKey(key, false) });
  db.prepare(`UPDATE api_key_management SET revoked_at = ? WHERE id = ?`)
    .run(nowIso(), req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'api_key.revoke', targetKind: 'api_key', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM api_key_management WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: visibleKey(refreshed, false) });
}

export function postKeys(req, res) { return createKey(req, res); }
export function patchKeys(req, res) { return rotateKey(req, res); }
export function getKeys(req, res) { return listKeys(req, res); }
