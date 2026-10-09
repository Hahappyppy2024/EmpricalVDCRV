import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, forbidden, notFound, unauthorized
} from '../errors.js';
import { requireString, optionalString } from '../services/validation.js';
import { recordEvent } from '../services/audit.js';
import { runCompletion, streamCompletion } from '../services/llm.js';
import { config } from '../config.js';

function nowIso() { return new Date().toISOString(); }
function newExecId() { return 'exe_' + crypto.randomBytes(8).toString('hex'); }
function newMsgId() { return 'msg_' + crypto.randomBytes(8).toString('hex'); }

export function listExecutions(req, res) {
  if (!req.user) throw unauthorized();
  const conversationId = optionalString(req.query?.conversation_id, 'conversation_id',
    { min: 1, max: 64 });
  const where = ['owner_id = ?'];
  const params = [req.user.id];
  if (conversationId) {
    where.push('conversation_id = ?'); params.push(conversationId);
  }
  const rows = getDb().prepare(`
    SELECT id, conversation_id, owner_id, model_id, prompt_tokens, completion_tokens,
           citations, created_at
    FROM chat_executions WHERE ${where.join(' AND ')}
    ORDER BY created_at DESC LIMIT 100
  `).all(...params);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export async function runChat(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const conversationId = requireString(req.body?.conversation_id, 'conversation_id',
    { min: 1, max: 64 });
  const prompt = requireString(req.body?.prompt, 'prompt', { min: 1, max: 16000 });
  const conv = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(conversationId);
  if (!conv) throw notFound('Conversation not found');
  if (conv.owner_id !== req.user.id) throw forbidden();
  // Store the user message first.
  db.prepare(`INSERT INTO conversation_messages
    (id, conversation_id, role, content, citations, tokens_used, created_at)
    VALUES (?,?,?,?,NULL,?,?)`).run(newMsgId(), conv.id, 'user', prompt,
    Math.max(1, Math.ceil(prompt.length / 4)), nowIso());
  const result = await runCompletion({
    prompt,
    systemPrompt: conv.safety_mode === 'strict' ? 'Be concise and safe.' : '',
    modelId: conv.model_id,
    temperature: conv.temperature,
    contextLength: conv.context_length,
    safetyMode: conv.safety_mode
  });
  const execId = newExecId();
  db.transaction(() => {
    db.prepare(`INSERT INTO chat_executions
      (id, conversation_id, owner_id, model_id, prompt_tokens, completion_tokens,
       citations, created_at)
      VALUES (?,?,?,?,?,?,?,?)`).run(
        execId, conv.id, req.user.id, conv.model_id,
        result.prompt_tokens, result.completion_tokens,
        JSON.stringify(result.citations), nowIso()
      );
    db.prepare(`INSERT INTO conversation_messages
      (id, conversation_id, role, content, citations, tokens_used, created_at)
      VALUES (?,?,?,?,?,?,?)`).run(
        newMsgId(), conv.id, 'assistant', result.content,
        JSON.stringify(result.citations), result.completion_tokens, nowIso()
      );
    db.prepare(`UPDATE conversations SET updated_at = ? WHERE id = ?`).run(nowIso(), conv.id);
    db.prepare(`INSERT INTO usage_records
      (id, user_id, conversation_id, model_id, prompt_tokens, completion_tokens, created_at)
      VALUES (?,?,?,?,?,?,?)`).run(
        newMsgId(), req.user.id, conv.id, conv.model_id,
        result.prompt_tokens, result.completion_tokens, nowIso()
      );
  })();
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'chat.execute', targetKind: 'conversation', targetId: conv.id,
    outcome: 'success', details: {
      execution_id: execId, prompt_tokens: result.prompt_tokens,
      completion_tokens: result.completion_tokens
    } });
  res.status(201).json({
    ok: true,
    data: {
      execution_id: execId,
      conversation_id: conv.id,
      model_id: conv.model_id,
      prompt_tokens: result.prompt_tokens,
      completion_tokens: result.completion_tokens,
      citations: result.citations,
      response: result.content
    }
  });
}

export function getExecution(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const row = db.prepare(`SELECT * FROM chat_executions WHERE id = ?`).get(req.params.id);
  if (!row) throw notFound('Execution not found');
  if (row.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  res.json({ ok: true, data: row });
}

export function postChat(req, res) { return runChat(req, res); }
export function getChat(req, res) { return listExecutions(req, res); }
