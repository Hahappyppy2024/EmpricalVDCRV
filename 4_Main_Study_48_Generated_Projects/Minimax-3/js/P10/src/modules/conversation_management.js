import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { badRequest, notFound, unauthorized, forbidden } from '../errors.js';
import {
  requireString, optionalString, optionalEnum, requireNumber, optionalNumber
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';
import { config } from '../config.js';

function newId() { return 'cnv_' + crypto.randomBytes(8).toString('hex'); }
function msgId() { return 'msg_' + crypto.randomBytes(8).toString('hex'); }
function nowIso() { return new Date().toISOString(); }

function loadOwned(db, id, userId) {
  return db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(id);
}

export function listConversations(req, res) {
  if (!req.user) throw unauthorized();
  const status = optionalEnum(req.query.status, 'status', ['active', 'archived', 'deleted']);
  const q = optionalString(req.query.q, 'q', { min: 1, max: 120 });
  const where = ['owner_id = ?'];
  const params = [req.user.id];
  if (status) { where.push('status = ?'); params.push(status); }
  if (q) {
    where.push('LOWER(title) LIKE ?');
    params.push(`%${q.toLowerCase()}%`);
  }
  const rows = getDb().prepare(`
    SELECT id, title, model_id, temperature, context_length, safety_mode, status, created_at, updated_at
    FROM conversations WHERE ${where.join(' AND ')}
    ORDER BY updated_at DESC
    LIMIT 200
  `).all(...params);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function createConversation(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const title = requireString(req.body?.title, 'title', { min: 1, max: 200 });
  const modelId = requireString(req.body?.model_id || config.defaultModelId, 'model_id', { min: 1, max: 80 });
  const temperature = optionalNumber(req.body?.temperature, 'temperature', { min: 0, max: 2 }) ?? config.defaultTemperature;
  const contextLength = optionalNumber(req.body?.context_length, 'context_length',
    { min: 64, max: 32768, integer: true }) ?? config.defaultContextLength;
  const safetyMode = optionalEnum(req.body?.safety_mode, 'safety_mode',
    ['standard', 'strict', 'off']) ?? 'standard';
  const id = newId();
  db.prepare(`INSERT INTO conversations
    (id, owner_id, title, model_id, temperature, context_length, safety_mode, status, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?, 'active', ?, ?)`).run(
      id, req.user.id, title, modelId, temperature, contextLength, safetyMode, nowIso(), nowIso()
    );
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'conversation.create', targetKind: 'conversation', targetId: id,
    outcome: 'success' });
  const conv = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: conv });
}

export function getConversation(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const conv = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(req.params.id);
  if (!conv) throw notFound('Conversation not found');
  if (conv.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const messages = db.prepare(`
    SELECT id, role, content, citations, tokens_used, created_at
    FROM conversation_messages WHERE conversation_id = ?
    ORDER BY created_at ASC
  `).all(conv.id);
  res.json({ ok: true, data: { ...conv, messages } });
}

export function updateConversation(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const conv = loadOwned(db, req.params.id);
  if (!conv) throw notFound('Conversation not found');
  if (conv.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const updates = [];
  const params = [];
  if (req.body?.title !== undefined) {
    updates.push('title = ?'); params.push(requireString(req.body.title, 'title', { min: 1, max: 200 }));
  }
  if (req.body?.status !== undefined) {
    const status = optionalEnum(req.body.status, 'status', ['active', 'archived', 'deleted']);
    if (!status) throw badRequest('Invalid status');
    updates.push('status = ?'); params.push(status);
  }
  if (req.body?.temperature !== undefined) {
    updates.push('temperature = ?');
    params.push(requireNumber(req.body.temperature, 'temperature', { min: 0, max: 2 }));
  }
  if (req.body?.context_length !== undefined) {
    updates.push('context_length = ?');
    params.push(requireNumber(req.body.context_length, 'context_length',
      { min: 64, max: 32768, integer: true }));
  }
  if (req.body?.safety_mode !== undefined) {
    updates.push('safety_mode = ?');
    params.push(optionalEnum(req.body.safety_mode, 'safety_mode', ['standard', 'strict', 'off']));
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(req.params.id);
  db.prepare(`UPDATE conversations SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'conversation.update', targetKind: 'conversation', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function appendMessage(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const conv = loadOwned(db, req.params.id);
  if (!conv) throw notFound('Conversation not found');
  if (conv.owner_id !== req.user.id) throw forbidden();
  const role = requireString(req.body?.role, 'role', { min: 1, max: 16 });
  if (!['user', 'assistant', 'system'].includes(role)) throw badRequest('Invalid role');
  const content = requireString(req.body?.content, 'content', { min: 1, max: 32000 });
  const citations = req.body?.citations ? JSON.stringify(req.body.citations) : null;
  const tokensUsed = Number.isFinite(Number(req.body?.tokens_used)) ? Number(req.body.tokens_used) : 0;
  const id = msgId();
  db.prepare(`INSERT INTO conversation_messages
    (id, conversation_id, role, content, citations, tokens_used, created_at)
    VALUES (?,?,?,?,?,?,?)`).run(id, conv.id, role, content, citations, tokensUsed, nowIso());
  db.prepare(`UPDATE conversations SET updated_at = ? WHERE id = ?`).run(nowIso(), conv.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'conversation.message', targetKind: 'conversation_message', targetId: id,
    outcome: 'success' });
  const stored = db.prepare(`SELECT id, role, content, citations, tokens_used, created_at
    FROM conversation_messages WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function postConversationMgmt(req, res) {
  // AI-02 POST /api/ai/conversation_management — creates a new conversation.
  return createConversation(req, res);
}

export function patchConversationMgmt(req, res) {
  return updateConversation(req, res);
}
