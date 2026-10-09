import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, forbidden, notFound, unauthorized
} from '../errors.js';
import { requireString, optionalString, optionalNumber } from '../services/validation.js';
import { recordEvent } from '../services/audit.js';

function nowIso() { return new Date().toISOString(); }

export function listShares(req, res) {
  if (!req.user) throw unauthorized();
  const rows = getDb().prepare(`
    SELECT id, conversation_id, owner_id, token, expires_at, revoked_at,
           view_count, created_at
    FROM share_conversations WHERE owner_id = ?
    ORDER BY created_at DESC LIMIT 200
  `).all(req.user.id);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function createShare(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const conversationId = requireString(req.body?.conversation_id, 'conversation_id',
    { min: 1, max: 64 });
  const ttlSeconds = optionalNumber(req.body?.ttl_seconds, 'ttl_seconds',
    { min: 60, max: 60 * 60 * 24 * 30, integer: true }) || 60 * 60 * 24 * 7;
  const conv = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(conversationId);
  if (!conv) throw notFound('Conversation not found');
  if (conv.owner_id !== req.user.id) throw forbidden();
  const id = 'shr_' + crypto.randomBytes(8).toString('hex');
  const token = 'shr_' + crypto.randomBytes(18).toString('base64url');
  const expiresAt = new Date(Date.now() + ttlSeconds * 1000).toISOString();
  db.prepare(`INSERT INTO share_conversations
    (id, conversation_id, owner_id, token, expires_at, view_count, created_at)
    VALUES (?,?,?,?,?,0,?)`).run(id, conv.id, req.user.id, token, expiresAt, nowIso());
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'share_conversation.create',
    targetKind: 'conversation', targetId: conv.id,
    outcome: 'success', details: { share_id: id, ttl_seconds: ttlSeconds } });
  res.status(201).json({
    ok: true,
    data: {
      id, conversation_id: conv.id, token, expires_at: expiresAt,
      public_path: `/share/${token}`
    }
  });
}

export function revokeShare(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const share = db.prepare(`SELECT * FROM share_conversations WHERE id = ?`).get(req.params.id);
  if (!share) throw notFound('Share not found');
  if (share.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  if (!share.revoked_at) {
    db.prepare(`UPDATE share_conversations SET revoked_at = ? WHERE id = ?`)
      .run(nowIso(), share.id);
    recordEvent({ actorId: req.user.id, actorRole: req.user.role,
      action: 'share_conversation.revoke',
      targetKind: 'conversation', targetId: share.conversation_id,
      outcome: 'success' });
  }
  const refreshed = db.prepare(`SELECT * FROM share_conversations WHERE id = ?`).get(share.id);
  res.json({ ok: true, data: refreshed });
}

export function viewShare(req, res) {
  // Public endpoint; validates the share token and returns sanitised transcript.
  const db = getDb();
  const token = requireString(req.params.token, 'token', { min: 6, max: 200 });
  const share = db.prepare(`SELECT * FROM share_conversations WHERE token = ?`).get(token);
  if (!share) throw notFound('Share link not found');
  if (share.revoked_at) throw forbidden('Share link revoked');
  if (share.expires_at && new Date(share.expires_at).getTime() < Date.now()) {
    throw forbidden('Share link expired');
  }
  const conv = db.prepare(`SELECT id, title, model_id, status FROM conversations WHERE id = ?`)
    .get(share.conversation_id);
  if (!conv) throw notFound('Conversation missing');
  const messages = db.prepare(`
    SELECT role, content, citations, created_at
    FROM conversation_messages WHERE conversation_id = ?
    ORDER BY created_at ASC
  `).all(conv.id);
  db.prepare(`UPDATE share_conversations SET view_count = view_count + 1 WHERE id = ?`)
    .run(share.id);
  recordEvent({ actorId: share.owner_id, actorRole: null,
    action: 'share_conversation.view',
    targetKind: 'share_conversation', targetId: share.id,
    outcome: 'info' });
  res.json({
    ok: true,
    data: {
      conversation: conv,
      messages,
      share: {
        created_at: share.created_at,
        expires_at: share.expires_at,
        view_count: share.view_count + 1
      }
    }
  });
}

export function postShares(req, res) { return createShare(req, res); }
export function patchShares(req, res) { return revokeShare(req, res); }
export function getShares(req, res) { return listShares(req, res); }
