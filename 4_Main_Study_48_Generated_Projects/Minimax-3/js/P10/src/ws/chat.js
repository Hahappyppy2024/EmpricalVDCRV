import crypto from 'node:crypto';
import { WebSocketServer } from 'ws';
import { getDb } from '../db/connection.js';
import { readSessionCookie, loadSessionUser } from '../services/session.js';
import { runCompletion, streamCompletion } from '../services/llm.js';
import { recordEvent } from '../services/audit.js';

function nowIso() { return new Date().toISOString(); }
function newId(prefix) { return prefix + '_' + crypto.randomBytes(8).toString('hex'); }

function authenticateUpgrade(req) {
  // The HTTP upgrade request carries the same cookies as regular requests.
  const sid = readSessionCookie(req);
  return loadSessionUser(sid);
}

export function attachWebSocketServer(httpServer) {
  const wss = new WebSocketServer({ noServer: true });
  httpServer.on('upgrade', (req, socket, head) => {
    const url = req.url || '';
    if (!url.startsWith('/ws/chat')) {
      socket.destroy();
      return;
    }
    const session = authenticateUpgrade(req);
    if (!session) {
      socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
      socket.destroy();
      return;
    }
    wss.handleUpgrade(req, socket, head, (ws) => {
      wss.emit('connection', ws, req, session);
    });
  });

  wss.on('connection', (ws, _req, session) => {
    ws.on('message', async (raw) => {
      let payload;
      try {
        payload = JSON.parse(raw.toString('utf8'));
      } catch (err) {
        ws.send(JSON.stringify({ event: 'error', message: 'Invalid JSON payload' }));
        return;
      }
      const { conversation_id: conversationId, prompt } = payload || {};
      if (typeof conversationId !== 'string' || typeof prompt !== 'string') {
        ws.send(JSON.stringify({ event: 'error', message: 'conversation_id and prompt required' }));
        return;
      }
      const db = getDb();
      const conv = db.prepare(`SELECT * FROM conversations WHERE id = ?`).get(conversationId);
      if (!conv) {
        ws.send(JSON.stringify({ event: 'error', message: 'Conversation not found' }));
        return;
      }
      if (conv.owner_id !== session.user.id) {
        ws.send(JSON.stringify({ event: 'error', message: 'Forbidden' }));
        return;
      }
      // Persist user message
      const userMsgId = newId('msg');
      db.prepare(`INSERT INTO conversation_messages
        (id, conversation_id, role, content, citations, tokens_used, created_at)
        VALUES (?,?,?,?,NULL,?,?)`).run(userMsgId, conv.id, 'user', prompt,
        Math.max(1, Math.ceil(prompt.length / 4)), nowIso());

      // Run deterministic completion
      const result = await runCompletion({
        prompt,
        systemPrompt: conv.safety_mode === 'strict' ? 'Be concise and safe.' : '',
        modelId: conv.model_id,
        temperature: conv.temperature,
        contextLength: conv.context_length,
        safetyMode: conv.safety_mode
      });
      const execId = newId('exe');
      const assistantMsgId = newId('msg');
      ws.send(JSON.stringify({ event: 'start', execution_id: execId,
        model_id: conv.model_id, prompt_tokens: result.prompt_tokens }));
      let buffer = '';
      await streamCompletion(result, (msg) => {
        if (msg.event === 'token') {
          buffer += msg.delta;
          ws.send(JSON.stringify({ event: 'token', delta: msg.delta }));
        } else if (msg.event === 'done') {
          db.transaction(() => {
            db.prepare(`INSERT INTO chat_executions
              (id, conversation_id, owner_id, model_id, prompt_tokens, completion_tokens,
               citations, created_at)
              VALUES (?,?,?,?,?,?,?,?)`).run(
                execId, conv.id, session.user.id, conv.model_id,
                result.prompt_tokens, result.completion_tokens,
                JSON.stringify(msg.citations), nowIso()
              );
            db.prepare(`INSERT INTO conversation_messages
              (id, conversation_id, role, content, citations, tokens_used, created_at)
              VALUES (?,?,?,?,?,?,?)`).run(
                assistantMsgId, conv.id, 'assistant', buffer,
                JSON.stringify(msg.citations), msg.completion_tokens, nowIso()
              );
            db.prepare(`UPDATE conversations SET updated_at = ? WHERE id = ?`)
              .run(nowIso(), conv.id);
            db.prepare(`INSERT INTO usage_records
              (id, user_id, conversation_id, model_id, prompt_tokens, completion_tokens, created_at)
              VALUES (?,?,?,?,?,?,?)`).run(
                newId('use'), session.user.id, conv.id, conv.model_id,
                result.prompt_tokens, msg.completion_tokens, nowIso()
              );
          })();
          recordEvent({ actorId: session.user.id, actorRole: session.user.role,
            action: 'chat.execute_ws', targetKind: 'conversation', targetId: conv.id,
            outcome: 'success', details: {
              execution_id: execId, prompt_tokens: result.prompt_tokens,
              completion_tokens: msg.completion_tokens
            } });
          ws.send(JSON.stringify({
            event: 'done',
            execution_id: execId,
            message_id: assistantMsgId,
            citations: msg.citations,
            completion_tokens: msg.completion_tokens
          }));
        }
      });
    });
  });

  return wss;
}
