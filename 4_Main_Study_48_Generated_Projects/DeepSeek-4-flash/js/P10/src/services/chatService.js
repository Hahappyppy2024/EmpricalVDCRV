import { HttpError } from '../middleware/error.js';
import { findBlockedTerm, generateResponse } from './modelAdapter.js';

export function createChatService(db, settings) {
  const insertExecution = db.prepare(
    `INSERT INTO chat_executions (user_id, conversation_id, prompt, response, citations, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, datetime('now'))`
  );
  const insertMessage = db.prepare(
    `INSERT INTO messages (conversation_id, role, content, citations, created_at)
     VALUES (?, ?, ?, ?, datetime('now'))`
  );
  const touchConversation = db.prepare(`UPDATE conversations SET updated_at = datetime('now') WHERE id = ?`);
  const getConversation = db.prepare('SELECT * FROM conversations WHERE id = ?');
  const getConfig = db.prepare('SELECT * FROM model_configurations WHERE id = ? AND user_id = ?');
  const getDefaultConfig = db.prepare('SELECT * FROM model_configurations WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1');
  const listEnabledTools = db.prepare(
    `SELECT tp.id, tp.name, tp.description, tp.config FROM user_tools ut
     JOIN tool_plugins tp ON tp.id = ut.tool_id
     WHERE ut.user_id = ? AND ut.enabled = 1 AND tp.enabled = 1 ORDER BY tp.id`
  );
  const listKnowledge = db.prepare(
    `SELECT kf.*, sf.original_name
     FROM knowledge_files kf JOIN stored_files sf ON sf.id = kf.stored_file_id
     WHERE kf.user_id = ? ORDER BY kf.id`
  );

  function searchKnowledge(userId, query, limit = 3) {
    const rows = listKnowledge.all(userId);
    const terms = String(query)
      .toLowerCase()
      .split(/\W+/)
      .filter((t) => t.length > 2);
    const scored = [];
    for (const row of rows) {
      const haystack = `${row.content ?? ''} ${row.description ?? ''}`.toLowerCase();
      const hits = terms.filter((t) => haystack.includes(t));
      const score = hits.length;
      if (score > 0) {
        const idx = haystack.indexOf(hits[0]);
        const start = Math.max(0, idx - 60);
        const snippet = `${(row.content ?? '').slice(start, start + 160)}...`;
        scored.push({ score, file: row.original_name ?? row.id, snippet: snippet || row.description || '', fileId: row.id, name: row.original_name });
      }
    }
    scored.sort((a, b) => b.score - a.score);
    return scored.slice(0, limit);
  }

  function resolveConfig(userId, configId) {
    if (configId) {
      const cfg = getConfig.get(configId, userId);
      if (!cfg) throw new HttpError(404, 'Model configuration not found', 'MODEL_CONFIG_NOT_FOUND');
      return cfg;
    }
    return getDefaultConfig.get(userId) ?? {
      provider: settings.get('default_provider'),
      model: settings.get('default_model'),
      temperature: 0.7,
      context_length: 4096,
      safety_mode: 'balanced',
    };
  }

  return {
    execute({ user, prompt, conversationId = null, collectionId = null, configId = null }) {
      if (!prompt || !String(prompt).trim()) {
        throw new HttpError(400, 'Prompt is required', 'VALIDATION_ERROR');
      }
      const blockedTerm = findBlockedTerm(prompt, settings.blockedTerms());
      if (blockedTerm) {
        insertExecution.run(user.id, conversationId ?? null, prompt, '', null, 'blocked');
        return { ok: false, blocked: true, blockedTerm };
      }

      const cfg = resolveConfig(user.id, configId);
      const modelAccess = settings.modelAccess();
      if (modelAccess.length && !modelAccess.includes(cfg.model)) {
        throw new HttpError(403, `Model "${cfg.model}" is not enabled for this workspace`, 'MODEL_ACCESS_DENIED');
      }

      let citations = [];
      let collectionUsed = null;
      if (collectionId) {
        const collection = db
          .prepare('SELECT * FROM retrieval_collections WHERE id = ? AND user_id = ?')
          .get(collectionId, user.id);
        if (!collection) {
          throw new HttpError(404, 'Retrieval collection not found', 'NOT_FOUND');
        }
        collectionUsed = collection;
        const files = db
          .prepare(
            `SELECT kf.*, sf.original_name FROM collection_files cf
             JOIN knowledge_files kf ON kf.id = cf.knowledge_file_id
             JOIN stored_files sf ON sf.id = kf.stored_file_id
             WHERE cf.collection_id = ?`
          )
          .all(collectionId);
        const found = [];
        for (const f of files) {
          const hits = searchInText(`${f.content ?? ''} ${f.description ?? ''}`, prompt);
          if (hits) {
            found.push({ file: f.original_name ?? `file-${f.id}`, snippet: snippetOf(f.content ?? '', prompt) });
          }
        }
        citations = found.slice(0, 3);
      }

      const tools = listEnabledTools.all(user.id);
      const toolResults = [];
      for (const tool of tools) {
        if (tool.name === 'web_search' && !collectionUsed) {
          const matches = searchKnowledge(user.id, prompt, 3);
          if (matches.length) {
            toolResults.push({ name: 'web_search', output: `found ${matches.length} knowledge match(es): ${matches.map((m) => m.file).join(', ')}` });
            citations = [...citations, ...matches.map((m) => ({ file: m.file, snippet: m.snippet }))];
          } else {
            toolResults.push({ name: 'web_search', output: 'no knowledge matches' });
          }
        }
        if (tool.name === 'calculator') {
          toolResults.push({ name: 'calculator', output: 'arithmetic evaluation available' });
        }
      }
      const uniqueCitations = [];
      for (const c of citations) {
        if (!uniqueCitations.some((u) => u.file === c.file && u.snippet === c.snippet)) {
          uniqueCitations.push(c);
        }
      }

      const response = generateResponse({
        prompt,
        model: cfg.model,
        provider: cfg.provider,
        temperature: cfg.temperature,
        contextLength: cfg.context_length,
        safetyMode: cfg.safety_mode,
        citations: uniqueCitations,
        toolResults,
        calculatorEnabled: tools.some((t) => t.name === 'calculator'),
      });

      const executionId = insertExecution.run(user.id, conversationId ?? null, prompt, response, JSON.stringify(uniqueCitations), 'completed').lastInsertRowid;

      if (conversationId) {
        const conv = getConversation.get(conversationId);
        if (conv && conv.user_id === user.id) {
          insertMessage.run(conversationId, 'user', prompt, null);
          insertMessage.run(conversationId, 'assistant', response, JSON.stringify(uniqueCitations));
          touchConversation.run(conversationId);
        }
      }

      const tokensUsed = Math.max(10, Math.round((prompt.length + response.length) / 3));

      return {
        ok: true,
        execution: {
          id: executionId,
          prompt,
          response,
          citations: uniqueCitations,
          model: cfg.model,
          provider: cfg.provider,
          collection: collectionUsed ? { id: collectionUsed.id, name: collectionUsed.name } : null,
          toolResults,
          tokensUsed,
          createdAt: new Date().toISOString(),
        },
      };
    },
    searchKnowledge,
  };
}

function searchInText(text, query) {
  const terms = String(query).toLowerCase().split(/\W+/).filter((t) => t.length > 2);
  const lower = String(text).toLowerCase();
  return terms.some((t) => lower.includes(t));
}

function snippetOf(text, query) {
  const lower = String(text).toLowerCase();
  const terms = String(query).toLowerCase().split(/\W+/).filter((t) => t.length > 2);
  let idx = -1;
  for (const t of terms) {
    const found = lower.indexOf(t);
    if (found >= 0) {
      idx = found;
      break;
    }
  }
  if (idx < 0) idx = 0;
  const start = Math.max(0, idx - 60);
  return `${text.slice(start, start + 180)}...`;
}
