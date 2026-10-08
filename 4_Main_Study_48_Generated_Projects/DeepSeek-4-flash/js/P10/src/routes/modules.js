import { Router } from 'express';
import crypto from 'node:crypto';
import { HttpError } from '../middleware/error.js';
import { requireAuth, requireAdmin } from '../middleware/auth.js';

function ownedRow(db, table, id, user) {
  const row = db.prepare(`SELECT * FROM ${table} WHERE id = ?`).get(id);
  if (!row) return null;
  if (user.role !== 'admin' && row.user_id !== user.id) return null;
  return row;
}

function isAdmin(user) {
  return user.role === 'admin';
}

function boundedLimit(value) {
  return Math.min(Math.max(Number(value) || 50, 1), 200);
}

function safeOffset(value) {
  return Math.max(Number(value) || 0, 0);
}

export function modulesRouter(db, { audit, usage, settings, chat, files, broadcast }) {
  const router = Router();
  router.use(requireAuth);

  const nowLog = () => new Date().toISOString();

  /* ------------------------------------------------------------------ */
  /* AI-01 Account access                                                */
  /* ------------------------------------------------------------------ */
  router.get('/account_access', (req, res) => {
    const limit = boundedLimit(req.query.limit);
    const offset = safeOffset(req.query.offset);
    const rows = isAdmin(req.user)
      ? db.prepare(`SELECT aa.*, u.username FROM account_access aa JOIN users u ON u.id = aa.user_id ORDER BY aa.id DESC LIMIT ? OFFSET ?`).all(limit, offset)
      : db.prepare(`SELECT aa.*, u.username FROM account_access aa JOIN users u ON u.id = aa.user_id WHERE aa.user_id = ? ORDER BY aa.id DESC LIMIT ? OFFSET ?`).all(req.user.id, limit, offset);
    res.json({ ok: true, items: rows });
  });

  router.post('/account_access', (req, res) => {
    const { action, detail } = req.body ?? {};
    if (!['register', 'login', 'logout', 'reset'].includes(action)) {
      throw new HttpError(400, 'action must be one of register, login, logout, reset', 'VALIDATION_ERROR');
    }
    const result = db
      .prepare(`INSERT INTO account_access (user_id, action, detail, ip, created_at) VALUES (?, ?, ?, ?, datetime('now'))`)
      .run(req.user.id, action, detail ?? null, req.ip ?? null);
    audit.record(req.user.id, action, 'account_access', { detail });
    usage.record(req.user.id, action, 'account_access', { detail: { id: result.lastInsertRowid } });
    res.status(201).json({ ok: true, id: result.lastInsertRowid, action, created_at: nowLog() });
  });

  router.patch('/account_access/:id', (req, res) => {
    const row = ownedRow(db, 'account_access', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Account access record not found', 'NOT_FOUND');
    const { action, detail } = req.body ?? {};
    if (action && !['register', 'login', 'logout', 'reset'].includes(action)) {
      throw new HttpError(400, 'action must be one of register, login, logout, reset', 'VALIDATION_ERROR');
    }
    db.prepare('UPDATE account_access SET action = COALESCE(?, action), detail = COALESCE(?, detail) WHERE id = ?')
      .run(action ?? null, detail ?? null, row.id);
    audit.record(req.user.id, 'update', 'account_access', { id: row.id });
    res.json({ ok: true, id: row.id });
  });

  /* ------------------------------------------------------------------ */
  /* AI-02 Conversation management                                       */
  /* ------------------------------------------------------------------ */
  router.get('/conversation_management', (req, res) => {
    const userId = isAdmin(req.user) && req.query.user_id ? Number(req.query.user_id) : req.user.id;
    const status = req.query.status === 'archived' || req.query.status === 'active' ? req.query.status : null;
    const search = req.query.search ? `%${req.query.search}%` : null;
    const clauses = ['c.user_id = ?'];
    const params = [userId];
    if (status) {
      clauses.push('c.status = ?');
      params.push(status);
    }
    if (search) {
      clauses.push('(c.title LIKE ?)');
      params.push(search);
    }
    const limit = boundedLimit(req.query.limit);
    const offset = safeOffset(req.query.offset);
    const rows = db
      .prepare(
        `SELECT c.*, (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS message_count
         FROM conversations c WHERE ${clauses.join(' AND ')} ORDER BY c.updated_at DESC LIMIT ? OFFSET ?`
      )
      .all(...params, limit, offset);
    res.json({ ok: true, items: rows });
  });

  router.get('/conversation_management/:id/messages', (req, res) => {
    const conv = ownedRow(db, 'conversations', req.params.id, req.user);
    if (!conv) throw new HttpError(404, 'Conversation not found', 'NOT_FOUND');
    const rows = db.prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id ASC').all(conv.id);
    res.json({
      ok: true,
      conversation: conv,
      items: rows.map((r) => ({ ...r, citations: r.citations ? JSON.parse(r.citations) : null })),
    });
  });

  router.post('/conversation_management', (req, res) => {
    const { title } = req.body ?? {};
    if (!title || !String(title).trim()) {
      throw new HttpError(400, 'title is required', 'VALIDATION_ERROR');
    }
    const result = db
      .prepare(`INSERT INTO conversations (user_id, title, status, created_at, updated_at) VALUES (?, ?, 'active', datetime('now'), datetime('now'))`)
      .run(req.user.id, String(title).trim());
    audit.record(req.user.id, 'create', 'conversation_management', { conversation_id: result.lastInsertRowid });
    usage.record(req.user.id, 'create', 'conversation_management', { detail: { title: String(title).trim() } });
    res.status(201).json({ ok: true, id: result.lastInsertRowid, title: String(title).trim() });
  });

  router.patch('/conversation_management/:id', (req, res) => {
    const row = ownedRow(db, 'conversations', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Conversation not found', 'NOT_FOUND');
    const { title, status } = req.body ?? {};
    if (title !== undefined && !String(title).trim()) {
      throw new HttpError(400, 'title cannot be empty', 'VALIDATION_ERROR');
    }
    if (status !== undefined && !['active', 'archived'].includes(status)) {
      throw new HttpError(400, 'status must be active or archived', 'VALIDATION_ERROR');
    }
    if (status === 'archived') {
      audit.record(req.user.id, 'archive', 'conversation_management', { conversation_id: row.id });
    }
    db.prepare('UPDATE conversations SET title = COALESCE(?, title), status = COALESCE(?, status), updated_at = datetime(\'now\') WHERE id = ?')
      .run(title !== undefined ? String(title).trim() : null, status ?? null, row.id);
    const updated = db.prepare('SELECT * FROM conversations WHERE id = ?').get(row.id);
    res.json({ ok: true, conversation: updated });
  });

  /* ------------------------------------------------------------------ */
  /* AI-03 Prompt templates                                              */
  /* ------------------------------------------------------------------ */
  router.get('/prompt_templates', (req, res) => {
    const limit = boundedLimit(req.query.limit);
    const offset = safeOffset(req.query.offset);
    const rows = db
      .prepare(
        `SELECT * FROM prompt_templates
         WHERE user_id = ? OR (is_public = 1 AND scope = 'shared')
         ORDER BY id DESC LIMIT ? OFFSET ?`
      )
      .all(req.user.id, limit, offset);
    res.json({ ok: true, items: rows });
  });

  router.post('/prompt_templates', (req, res) => {
    const { title, content } = req.body ?? {};
    if (!title || !String(title).trim() || !content || !String(content).trim()) {
      throw new HttpError(400, 'title and content are required', 'VALIDATION_ERROR');
    }
    const result = db
      .prepare(`INSERT INTO prompt_templates (user_id, title, content, scope, is_public, created_by, created_at) VALUES (?, ?, ?, 'private', 0, 'user', datetime('now'))`)
      .run(req.user.id, String(title).trim(), String(content));
    audit.record(req.user.id, 'create', 'prompt_templates', { template_id: result.lastInsertRowid });
    res.status(201).json({ ok: true, id: result.lastInsertRowid });
  });

  router.patch('/prompt_templates/:id', (req, res) => {
    const row = db.prepare('SELECT * FROM prompt_templates WHERE id = ?').get(req.params.id);
    if (!row) throw new HttpError(404, 'Prompt template not found', 'NOT_FOUND');
    if (!isAdmin(req.user) && row.user_id !== req.user.id) {
      throw new HttpError(403, 'Cannot modify another user\'s template', 'FORBIDDEN');
    }
    const { title, content, scope, is_public } = req.body ?? {};
    if (scope !== undefined && !['private', 'shared'].includes(scope)) {
      throw new HttpError(400, 'scope must be private or shared', 'VALIDATION_ERROR');
    }
    if ((scope === 'shared' || is_public === true) && !isAdmin(req.user)) {
      throw new HttpError(403, 'Only administrators can publish shared templates', 'ADMIN_REQUIRED');
    }
    db.prepare(
      `UPDATE prompt_templates SET title = COALESCE(?, title), content = COALESCE(?, content),
       scope = COALESCE(?, scope), is_public = COALESCE(?, is_public) WHERE id = ?`
    ).run(
      title !== undefined ? String(title).trim() : null,
      content !== undefined ? String(content) : null,
      scope ?? null,
      is_public === undefined ? null : (is_public ? 1 : 0),
      row.id
    );
    audit.record(req.user.id, 'update', 'prompt_templates', { template_id: row.id });
    res.json({ ok: true, template: db.prepare('SELECT * FROM prompt_templates WHERE id = ?').get(row.id) });
  });

  /* ------------------------------------------------------------------ */
  /* AI-04 Model configuration                                           */
  /* ------------------------------------------------------------------ */
  const MODEL_ACCESS_HELPER = null;
  router.get('/model_configuration', (req, res) => {
    const userId = isAdmin(req.user) && req.query.user_id ? Number(req.query.user_id) : req.user.id;
    const rows = db.prepare('SELECT * FROM model_configurations WHERE user_id = ? ORDER BY is_default DESC, id ASC').all(userId);
    res.json({ ok: true, items: rows, model_access: settings.modelAccess() });
  });

  router.post('/model_configuration', (req, res) => {
    const { provider, model, temperature, context_length, safety_mode, is_default } = req.body ?? {};
    if (!provider || !model) {
      throw new HttpError(400, 'provider and model are required', 'VALIDATION_ERROR');
    }
    const temp = temperature === undefined ? 0.7 : Number(temperature);
    const ctx = context_length === undefined ? 4096 : Number(context_length);
    if (Number.isNaN(temp) || temp < 0 || temp > 2) {
      throw new HttpError(400, 'temperature must be between 0 and 2', 'VALIDATION_ERROR');
    }
    if (Number.isNaN(ctx) || ctx < 256 || ctx > 200000) {
      throw new HttpError(400, 'context_length must be between 256 and 200000', 'VALIDATION_ERROR');
    }
    if (safety_mode !== undefined && !['strict', 'balanced', 'off'].includes(safety_mode)) {
      throw new HttpError(400, 'safety_mode must be strict, balanced, or off', 'VALIDATION_ERROR');
    }
    const access = settings.modelAccess();
    if (access.length && !access.includes(model)) {
      throw new HttpError(403, `Model "${model}" is not enabled by the administrator`, 'MODEL_ACCESS_DENIED');
    }
    let defaultFlag = is_default ? 1 : 0;
    if (defaultFlag) {
      db.prepare('UPDATE model_configurations SET is_default = 0 WHERE user_id = ?').run(req.user.id);
    }
    const result = db
      .prepare(`INSERT INTO model_configurations (user_id, provider, model, temperature, context_length, safety_mode, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))`)
      .run(req.user.id, provider, model, temp, ctx, safety_mode ?? 'balanced', defaultFlag);
    audit.record(req.user.id, 'create', 'model_configuration', { config_id: result.lastInsertRowid });
    res.status(201).json({ ok: true, id: result.lastInsertRowid });
  });

  router.patch('/model_configuration/:id', (req, res) => {
    const row = ownedRow(db, 'model_configurations', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Model configuration not found', 'NOT_FOUND');
    const { provider, model, temperature, context_length, safety_mode, is_default } = req.body ?? {};
    if (temperature !== undefined && (Number.isNaN(Number(temperature)) || Number(temperature) < 0 || Number(temperature) > 2)) {
      throw new HttpError(400, 'temperature must be between 0 and 2', 'VALIDATION_ERROR');
    }
    if (context_length !== undefined && (Number.isNaN(Number(context_length)) || Number(context_length) < 256)) {
      throw new HttpError(400, 'context_length must be at least 256', 'VALIDATION_ERROR');
    }
    if (safety_mode !== undefined && !['strict', 'balanced', 'off'].includes(safety_mode)) {
      throw new HttpError(400, 'safety_mode must be strict, balanced, or off', 'VALIDATION_ERROR');
    }
    if (model) {
      const access = settings.modelAccess();
      if (access.length && !access.includes(model)) {
        throw new HttpError(403, `Model "${model}" is not enabled by the administrator`, 'MODEL_ACCESS_DENIED');
      }
    }
    if (is_default) {
      db.prepare('UPDATE model_configurations SET is_default = 0 WHERE user_id = ?').run(row.user_id);
    }
    db.prepare(
      `UPDATE model_configurations SET provider = COALESCE(?, provider), model = COALESCE(?, model),
       temperature = COALESCE(?, temperature), context_length = COALESCE(?, context_length),
       safety_mode = COALESCE(?, safety_mode), is_default = CASE WHEN ? = 1 THEN 1 ELSE is_default END
       WHERE id = ?`
    ).run(
      provider ?? null,
      model ?? null,
      temperature === undefined ? null : Number(temperature),
      context_length === undefined ? null : Number(context_length),
      safety_mode ?? null,
      is_default ? 1 : 0,
      row.id
    );
    if (isAdmin(req.user) && row.user_id !== req.user.id) {
      audit.record(req.user.id, 'update', 'model_configuration', { config_id: row.id, user_id: row.user_id });
    }
    res.json({ ok: true, config: db.prepare('SELECT * FROM model_configurations WHERE id = ?').get(row.id) });
  });

  /* ------------------------------------------------------------------ */
  /* AI-05 Knowledge file upload                                         */
  /* ------------------------------------------------------------------ */
  router.get('/knowledge_file_upload', (req, res) => {
    const rows = db
      .prepare(
        `SELECT kf.id, kf.user_id, kf.description, kf.created_at, sf.original_name, sf.size, sf.mime_type, sf.status AS file_status
         FROM knowledge_files kf JOIN stored_files sf ON sf.id = kf.stored_file_id
         WHERE kf.user_id = ? ORDER BY kf.id DESC LIMIT ? OFFSET ?`
      )
      .all(req.user.id, boundedLimit(req.query.limit), safeOffset(req.query.offset));
    res.json({ ok: true, items: rows, max_upload_size: settings.get('max_upload_size') });
  });

  router.post('/knowledge_file_upload', (req, res, next) => {
    files.uploadMiddleware(req, res, async (err) => {
      if (err) return next(err instanceof HttpError ? err : new HttpError(400, err.message, 'UPLOAD_ERROR'));
      try {
        const created = await files.create({ userId: req.user.id, file: req.file, description: req.body.description });
        audit.record(req.user.id, 'upload', 'knowledge_file_upload', { file: created.storedFile.original_name, id: created.knowledgeFileId });
        usage.record(req.user.id, 'upload', 'knowledge_file_upload', { detail: { file: created.storedFile.original_name, size: created.size } });
        res.status(201).json({
          ok: true,
          id: created.knowledgeFileId,
          stored_file_id: created.storedFile.id,
          original_name: created.storedFile.original_name,
          size: created.size,
          mime_type: created.storedFile.mime_type,
        });
      } catch (uploadErr) {
        next(uploadErr);
      }
    });
  });

  router.patch('/knowledge_file_upload/:id', (req, res) => {
    const { description } = req.body ?? {};
    const updated = files.updateDescription({ knowledgeFileId: Number(req.params.id), userId: req.user.id, description });
    res.json({ ok: true, knowledge_file: updated });
  });

  /* ------------------------------------------------------------------ */
  /* AI-06 Retrieval collection                                          */
  /* ------------------------------------------------------------------ */
  router.get('/retrieval_collection', (req, res) => {
    const search = req.query.search ? String(req.query.search).trim() : null;
    const limit = boundedLimit(req.query.limit);
    if (search) {
      const matches = chat.searchKnowledge(req.user.id, search, limit);
      usage.record(req.user.id, 'retrieve', 'retrieval_collection', { detail: { query: search, results: matches.length } });
      res.json({ ok: true, search: search, items: matches });
      return;
    }
    const rows = db
      .prepare(
        `SELECT rc.*, (SELECT COUNT(*) FROM collection_files cf WHERE cf.collection_id = rc.id) AS file_count
         FROM retrieval_collections rc WHERE rc.user_id = ? ORDER BY rc.id DESC LIMIT ? OFFSET ?`
      )
      .all(req.user.id, limit, safeOffset(req.query.offset));
    res.json({ ok: true, items: rows });
  });

  router.get('/retrieval_collection/:id', (req, res) => {
    const row = ownedRow(db, 'retrieval_collections', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Retrieval collection not found', 'NOT_FOUND');
    const files = db
      .prepare(
        `SELECT kf.id, kf.description, kf.created_at, sf.original_name, sf.size, sf.mime_type
         FROM collection_files cf JOIN knowledge_files kf ON kf.id = cf.knowledge_file_id
         JOIN stored_files sf ON sf.id = kf.stored_file_id WHERE cf.collection_id = ? ORDER BY cf.id`
      )
      .all(row.id);
    res.json({ ok: true, collection: row, files });
  });

  router.post('/retrieval_collection', (req, res) => {
    const { name, description, file_ids } = req.body ?? {};
    if (!name || !String(name).trim()) {
      throw new HttpError(400, 'name is required', 'VALIDATION_ERROR');
    }
    const insertCollection = db.prepare(
      `INSERT INTO retrieval_collections (user_id, name, description, created_at) VALUES (?, ?, ?, datetime('now'))`
    );
    const insertLink = db.prepare(
      `INSERT OR IGNORE INTO collection_files (collection_id, knowledge_file_id) VALUES (?, ?)`
    );
    const checkOwnership = db.prepare('SELECT id FROM knowledge_files WHERE id = ? AND user_id = ?');
    const tx = db.transaction(() => {
      const id = insertCollection.run(req.user.id, String(name).trim(), description ?? null).lastInsertRowid;
      for (const fileId of file_ids || []) {
        if (checkOwnership.get(Number(fileId), req.user.id)) {
          insertLink.run(id, Number(fileId));
        }
      }
      return id;
    });
    const id = tx();
    audit.record(req.user.id, 'create', 'retrieval_collection', { collection_id: id });
    res.status(201).json({ ok: true, id });
  });

  router.patch('/retrieval_collection/:id', (req, res) => {
    const row = ownedRow(db, 'retrieval_collections', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Retrieval collection not found', 'NOT_FOUND');
    const { name, description, add_files, remove_files } = req.body ?? {};
    const checkOwnership = db.prepare('SELECT id FROM knowledge_files WHERE id = ? AND user_id = ?');
    const tx = db.transaction(() => {
      db.prepare('UPDATE retrieval_collections SET name = COALESCE(?, name), description = COALESCE(?, description) WHERE id = ?')
        .run(name !== undefined ? String(name).trim() : null, description !== undefined ? description : null, row.id);
      for (const fileId of add_files || []) {
        if (checkOwnership.get(Number(fileId), req.user.id)) {
          db.prepare('INSERT OR IGNORE INTO collection_files (collection_id, knowledge_file_id) VALUES (?, ?)').run(row.id, Number(fileId));
        }
      }
      for (const fileId of remove_files || []) {
        db.prepare('DELETE FROM collection_files WHERE collection_id = ? AND knowledge_file_id = ?').run(row.id, Number(fileId));
      }
    });
    tx();
    audit.record(req.user.id, 'update', 'retrieval_collection', { collection_id: row.id });
    res.json({ ok: true, collection: db.prepare('SELECT * FROM retrieval_collections WHERE id = ?').get(row.id) });
  });

  /* ------------------------------------------------------------------ */
  /* AI-07 Tool/plugin registry                                          */
  /* ------------------------------------------------------------------ */
  router.get('/tool_plugin_registry', (req, res) => {
    const rows = db
      .prepare(
        `SELECT tp.*, COALESCE(ut.enabled, 0) AS user_enabled
         FROM tool_plugins tp
         LEFT JOIN user_tools ut ON ut.tool_id = tp.id AND ut.user_id = ?
         ORDER BY tp.id`
      )
      .all(req.user.id);
    res.json({ ok: true, items: rows });
  });

  router.post('/tool_plugin_registry', requireAdmin, (req, res) => {
    const { name, description, config } = req.body ?? {};
    if (!name || !String(name).trim()) {
      throw new HttpError(400, 'name is required', 'VALIDATION_ERROR');
    }
    let cfg = '{}';
    if (config !== undefined) {
      try {
        cfg = typeof config === 'string' ? config : JSON.stringify(config);
        JSON.parse(cfg);
      } catch {
        throw new HttpError(400, 'config must be valid JSON', 'VALIDATION_ERROR');
      }
    }
    const result = db
      .prepare(`INSERT INTO tool_plugins (name, description, config, enabled, created_by, created_at) VALUES (?, ?, ?, 1, ?, datetime('now'))`)
      .run(String(name).trim(), description ?? null, cfg, req.user.id);
    audit.record(req.user.id, 'create', 'tool_plugin_registry', { tool_id: result.lastInsertRowid, name: String(name).trim() });
    res.status(201).json({ ok: true, id: result.lastInsertRowid });
  });

  router.patch('/tool_plugin_registry/:id', (req, res) => {
    const row = db.prepare('SELECT * FROM tool_plugins WHERE id = ?').get(req.params.id);
    if (!row) throw new HttpError(404, 'Tool not found', 'NOT_FOUND');
    const { enabled, user_enabled, name, description, config } = req.body ?? {};
    if (user_enabled !== undefined) {
      if (isAdmin(req.user) && req.body.user_id) {
        db.prepare(`INSERT INTO user_tools (user_id, tool_id, enabled) VALUES (?, ?, ?)
                    ON CONFLICT(user_id, tool_id) DO UPDATE SET enabled = excluded.enabled`)
          .run(Number(req.body.user_id), row.id, user_enabled ? 1 : 0);
      } else {
        db.prepare(`INSERT INTO user_tools (user_id, tool_id, enabled) VALUES (?, ?, ?)
                    ON CONFLICT(user_id, tool_id) DO UPDATE SET enabled = excluded.enabled`)
          .run(req.user.id, row.id, user_enabled ? 1 : 0);
      }
      res.json({ ok: true, tool_id: row.id, user_enabled: user_enabled ? 1 : 0 });
      return;
    }
    if (!isAdmin(req.user)) {
      throw new HttpError(403, 'Only administrators can configure tools', 'ADMIN_REQUIRED');
    }
    let cfg = null;
    if (config !== undefined) {
      try {
        cfg = typeof config === 'string' ? config : JSON.stringify(config);
        JSON.parse(cfg);
      } catch {
        throw new HttpError(400, 'config must be valid JSON', 'VALIDATION_ERROR');
      }
    }
    if (enabled !== undefined && typeof enabled !== 'boolean') {
      throw new HttpError(400, 'enabled must be a boolean', 'VALIDATION_ERROR');
    }
    db.prepare('UPDATE tool_plugins SET name = COALESCE(?, name), description = COALESCE(?, description), config = COALESCE(?, config), enabled = COALESCE(?, enabled) WHERE id = ?')
      .run(name !== undefined ? String(name).trim() : null, description !== undefined ? description : null, cfg, enabled === undefined ? null : (enabled ? 1 : 0), row.id);
    audit.record(req.user.id, 'update', 'tool_plugin_registry', { tool_id: row.id });
    res.json({ ok: true, tool: db.prepare('SELECT * FROM tool_plugins WHERE id = ?').get(row.id) });
  });

  /* ------------------------------------------------------------------ */
  /* AI-08 API key management                                            */
  /* ------------------------------------------------------------------ */
  router.get('/api_key_management', (req, res) => {
    const userId = isAdmin(req.user) && req.query.user_id ? Number(req.query.user_id) : req.user.id;
    const rows = db
      .prepare('SELECT id, user_id, provider, key_prefix, masked_key, status, last_used_at, created_at FROM api_keys WHERE user_id = ? ORDER BY id DESC')
      .all(userId);
    res.json({ ok: true, items: rows });
  });

  function maskKey(provider, key) {
    const safe = String(key);
    const prefix = `${provider.replace(/[^a-z0-9]/gi, '').slice(0, 4).toLowerCase()}-${safe.slice(0, 6).replace(/[^a-zA-Z0-9]/g, '_') || 'key'}`;
    const tail = safe.length > 4 ? safe.slice(-4) : safe;
    const masked = `${safe.slice(0, 2)}****...${tail}`;
    return { prefix, masked };
  }

  router.post('/api_key_management', (req, res) => {
    const { provider, key } = req.body ?? {};
    if (!provider || !key || !String(key).trim()) {
      throw new HttpError(400, 'provider and key are required', 'VALIDATION_ERROR');
    }
    const { prefix, masked } = maskKey(provider, String(key));
    const result = db
      .prepare(`INSERT INTO api_keys (user_id, provider, key_prefix, masked_key, status, created_at) VALUES (?, ?, ?, ?, 'active', datetime('now'))`)
      .run(req.user.id, provider, prefix, masked);
    audit.record(req.user.id, 'create', 'api_key_management', { provider, prefix });
    res.status(201).json({ ok: true, id: result.lastInsertRowid, provider, key_prefix: prefix, masked_key: masked, status: 'active' });
  });

  router.patch('/api_key_management/:id', (req, res) => {
    const row = ownedRow(db, 'api_keys', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'API key not found', 'NOT_FOUND');
    const { action, key } = req.body ?? {};
    if (!['rotate', 'revoke'].includes(action)) {
      throw new HttpError(400, 'action must be rotate or revoke', 'VALIDATION_ERROR');
    }
    if (action === 'rotate') {
      if (!key || !String(key).trim()) {
        throw new HttpError(400, 'a new key value is required to rotate', 'VALIDATION_ERROR');
      }
      const { prefix, masked } = maskKey(row.provider, String(key));
      db.prepare('UPDATE api_keys SET key_prefix = ?, masked_key = ?, status = \'active\', last_used_at = NULL WHERE id = ?').run(prefix, masked, row.id);
      audit.record(req.user.id, 'rotate', 'api_key_management', { key_id: row.id });
      res.json({ ok: true, id: row.id, action: 'rotate', key_prefix: prefix, masked_key: masked, status: 'active' });
      return;
    }
    db.prepare("UPDATE api_keys SET status = 'revoked' WHERE id = ?").run(row.id);
    audit.record(req.user.id, 'revoke', 'api_key_management', { key_id: row.id });
    res.json({ ok: true, id: row.id, action: 'revoke', status: 'revoked' });
  });

  /* ------------------------------------------------------------------ */
  /* AI-09 Chat execution                                                */
  /* ------------------------------------------------------------------ */
  router.get('/chat_execution', (req, res) => {
    const userId = isAdmin(req.user) && req.query.user_id ? Number(req.query.user_id) : req.user.id;
    const clauses = ['ce.user_id = ?'];
    const params = [userId];
    if (req.query.conversation_id) {
      clauses.push('ce.conversation_id = ?');
      params.push(Number(req.query.conversation_id));
    }
    const rows = db
      .prepare(
        `SELECT ce.*, c.title AS conversation_title FROM chat_executions ce
         LEFT JOIN conversations c ON c.id = ce.conversation_id
         WHERE ${clauses.join(' AND ')} ORDER BY ce.id DESC LIMIT ? OFFSET ?`
      )
      .all(...params, boundedLimit(req.query.limit), safeOffset(req.query.offset));
    res.json({
      ok: true,
      items: rows.map((r) => ({ ...r, citations: r.citations ? JSON.parse(r.citations) : null })),
    });
  });

  router.post('/chat_execution', (req, res, next) => {
    try {
      const { prompt, conversation_id, collection_id, config_id } = req.body ?? {};
      const result = chat.execute({
        user: req.user,
        prompt,
        conversationId: conversation_id ?? null,
        collectionId: collection_id ?? null,
        configId: config_id ?? null,
      });
      if (result.ok) {
        usage.record(req.user.id, 'chat', 'chat_execution', { tokensUsed: result.execution.tokensUsed, detail: { execution_id: result.execution.id } });
        audit.record(req.user.id, 'chat', 'chat_execution', { execution_id: result.execution.id, model: result.execution.model });
        try {
          broadcast('chat:execution', { execution: result.execution, user_id: req.user.id });
        } catch {
          /* realtime is best-effort */
        }
        res.status(201).json({ ok: true, ...result.execution });
      } else {
        audit.record(req.user.id, 'blocked', 'chat_execution', { term: result.blockedTerm });
        usage.record(req.user.id, 'blocked', 'chat_execution', { detail: { term: result.blockedTerm } });
        res.status(200).json({ ok: false, blocked: true, blockedTerm: result.blockedTerm });
      }
    } catch (err) {
      next(err);
    }
  });

  router.patch('/chat_execution/:id', (req, res) => {
    const row = ownedRow(db, 'chat_executions', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Chat execution not found', 'NOT_FOUND');
    const { status } = req.body ?? {};
    if (!['completed', 'blocked', 'failed'].includes(status)) {
      throw new HttpError(400, 'status must be completed, blocked, or failed', 'VALIDATION_ERROR');
    }
    db.prepare('UPDATE chat_executions SET status = ? WHERE id = ?').run(status, row.id);
    res.json({ ok: true, id: row.id, status });
  });

  /* ------------------------------------------------------------------ */
  /* AI-10 Share conversation                                            */
  /* ------------------------------------------------------------------ */
  router.get('/share_conversation', (req, res) => {
    const userId = isAdmin(req.user) && req.query.user_id ? Number(req.query.user_id) : req.user.id;
    const rows = db
      .prepare(
        `SELECT sc.*, c.title AS conversation_title FROM share_conversations sc
         JOIN conversations c ON c.id = sc.conversation_id WHERE sc.user_id = ? ORDER BY sc.id DESC LIMIT ? OFFSET ?`
      )
      .all(userId, boundedLimit(req.query.limit), safeOffset(req.query.offset));
    res.json({ ok: true, items: rows });
  });

  router.post('/share_conversation', (req, res) => {
    const { conversation_id, expires_at } = req.body ?? {};
    if (!conversation_id) {
      throw new HttpError(400, 'conversation_id is required', 'VALIDATION_ERROR');
    }
    const conv = db.prepare('SELECT * FROM conversations WHERE id = ?').get(Number(conversation_id));
    if (!conv || conv.user_id !== req.user.id) {
      throw new HttpError(404, 'Conversation not found', 'NOT_FOUND');
    }
    const existing = db
      .prepare('SELECT * FROM share_conversations WHERE user_id = ? AND conversation_id = ? AND revoked = 0 ORDER BY id DESC LIMIT 1')
      .get(req.user.id, conv.id);
    if (existing) {
      res.status(200).json({ ok: true, id: existing.id, token: existing.token, conversation_id: existing.conversation_id, reused: true });
      return;
    }
    const token = crypto.randomBytes(6).toString('hex');
    const result = db
      .prepare(`INSERT INTO share_conversations (user_id, conversation_id, token, expires_at, revoked, created_at) VALUES (?, ?, ?, ?, 0, datetime('now'))`)
      .run(req.user.id, conv.id, `share-${token}`, expires_at ?? null);
    audit.record(req.user.id, 'share', 'share_conversation', { share_id: result.lastInsertRowid, conversation_id: conv.id });
    res.status(201).json({ ok: true, id: result.lastInsertRowid, token: `share-${token}`, conversation_id: conv.id });
  });

  router.patch('/share_conversation/:id', (req, res) => {
    const row = ownedRow(db, 'share_conversations', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Share record not found', 'NOT_FOUND');
    const { revoked, expires_at } = req.body ?? {};
    if (revoked !== undefined && typeof revoked !== 'boolean') {
      throw new HttpError(400, 'revoked must be a boolean', 'VALIDATION_ERROR');
    }
    db.prepare('UPDATE share_conversations SET revoked = COALESCE(?, revoked), expires_at = COALESCE(?, expires_at) WHERE id = ?')
      .run(revoked === undefined ? null : (revoked ? 1 : 0), expires_at ?? null, row.id);
    if (revoked) {
      audit.record(req.user.id, 'revoke', 'share_conversation', { share_id: row.id, token: row.token });
    }
    res.json({ ok: true, share: db.prepare('SELECT * FROM share_conversations WHERE id = ?').get(row.id) });
  });

  /* ------------------------------------------------------------------ */
  /* AI-11 Usage and audit logs                                          */
  /* ------------------------------------------------------------------ */
  router.get('/usage_and_audit_logs', (req, res) => {
    const type = req.query.type === 'audit' ? 'audit' : 'usage';
    const module = req.query.module ?? null;
    const action = req.query.action ?? null;
    if (type === 'audit') {
      const rows = audit.list({
        userId: req.user.id,
        module,
        action,
        limit: req.query.limit,
        offset: req.query.offset,
        actorOnly: !isAdmin(req.user),
      });
      res.json({ ok: true, type: 'audit', items: rows });
      return;
    }
    const rows = usage.list({
      userId: req.user.id,
      module,
      action,
      from: req.query.from ?? null,
      to: req.query.to ?? null,
      limit: req.query.limit,
      offset: req.query.offset,
      actorOnly: !isAdmin(req.user),
    });
    res.json({ ok: true, type: 'usage', items: rows, summary: usage.summarize(req.user.id) });
  });

  router.post('/usage_and_audit_logs', (req, res) => {
    const { type, action, module, detail, tokens_used, user_id } = req.body ?? {};
    if (type === 'audit') {
      if (!isAdmin(req.user)) {
        throw new HttpError(403, 'Only administrators can record audit events', 'ADMIN_REQUIRED');
      }
      if (!action || !module) {
        throw new HttpError(400, 'action and module are required', 'VALIDATION_ERROR');
      }
      audit.record(user_id ?? req.user.id, action, module, detail ?? null);
      res.status(201).json({ ok: true, type: 'audit' });
      return;
    }
    if (!action || !module) {
      throw new HttpError(400, 'action and module are required', 'VALIDATION_ERROR');
    }
    const result = db
      .prepare(`INSERT INTO usage_logs (user_id, action, module, tokens_used, detail, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))`)
      .run(isAdmin(req.user) && user_id ? Number(user_id) : req.user.id, action, module, Number(tokens_used) || 0, detail ? JSON.stringify(detail) : null);
    res.status(201).json({ ok: true, id: result.lastInsertRowid, type: 'usage' });
  });

  router.patch('/usage_and_audit_logs/:id', (req, res) => {
    const type = req.query.type === 'audit' ? 'audit' : 'usage';
    const { detail } = req.body ?? {};
    if (type === 'audit') {
      const row = db.prepare('SELECT * FROM audit_events WHERE id = ?').get(req.params.id);
      if (!row) throw new HttpError(404, 'Audit event not found', 'NOT_FOUND');
      if (!isAdmin(req.user)) throw new HttpError(403, 'Only administrators can modify audit events', 'ADMIN_REQUIRED');
      db.prepare('UPDATE audit_events SET detail = COALESCE(?, detail) WHERE id = ?').run(detail ? JSON.stringify(detail) : null, row.id);
      res.json({ ok: true, id: row.id, type: 'audit' });
      return;
    }
    const row = ownedRow(db, 'usage_logs', req.params.id, req.user);
    if (!row) throw new HttpError(404, 'Usage log not found', 'NOT_FOUND');
    db.prepare('UPDATE usage_logs SET detail = COALESCE(?, detail) WHERE id = ?').run(detail ? JSON.stringify(detail) : null, row.id);
    res.json({ ok: true, id: row.id, type: 'usage' });
  });

  /* ------------------------------------------------------------------ */
  /* AI-12 Admin moderation and settings                                 */
  /* ------------------------------------------------------------------ */
  router.get('/admin_moderation_and_settings', requireAdmin, (req, res) => {
    const users = db
      .prepare(`SELECT id, username, email, role, status, created_at FROM users ORDER BY id`)
      .all();
    res.json({
      ok: true,
      settings: settings.list(),
      users,
      blocked_terms: settings.blockedTerms(),
      model_access: settings.modelAccess(),
    });
  });

  router.post('/admin_moderation_and_settings', requireAdmin, (req, res) => {
    const { setting_key, setting_value } = req.body ?? {};
    if (!setting_key) {
      throw new HttpError(400, 'setting_key is required', 'VALIDATION_ERROR');
    }
    let value = setting_value;
    if (['blocked_terms', 'model_access'].includes(setting_key)) {
      if (!Array.isArray(value)) {
        throw new HttpError(400, `${setting_key} must be an array`, 'VALIDATION_ERROR');
      }
    }
    if (setting_key === 'max_upload_size' && (Number.isNaN(Number(value)) || Number(value) <= 0)) {
      throw new HttpError(400, 'max_upload_size must be a positive number', 'VALIDATION_ERROR');
    }
    settings.set(setting_key, value, req.user.id);
    audit.record(req.user.id, 'update', 'admin_moderation_and_settings', { setting_key, value });
    res.status(201).json({ ok: true, setting_key, setting_value: value });
  });

  router.patch('/admin_moderation_and_settings/:id', requireAdmin, (req, res) => {
    const { action, status, setting_key, setting_value, terms } = req.body ?? {};
    const id = Number(req.params.id);
    if (action === 'user_status') {
      const user = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
      if (!user) throw new HttpError(404, 'User not found', 'NOT_FOUND');
      if (!['active', 'suspended'].includes(status)) {
        throw new HttpError(400, 'status must be active or suspended', 'VALIDATION_ERROR');
      }
      db.prepare('UPDATE users SET status = ? WHERE id = ?').run(status, id);
      if (status === 'suspended') {
        db.prepare('DELETE FROM sessions WHERE user_id = ?').run(id);
      }
      audit.record(req.user.id, 'user_status', 'admin_moderation_and_settings', { user_id: id, status });
      res.json({ ok: true, user: db.prepare('SELECT id, username, email, role, status FROM users WHERE id = ?').get(id) });
      return;
    }
    if (action === 'update_setting') {
      const settingRow = db.prepare('SELECT * FROM admin_settings WHERE id = ?').get(id);
      if (!settingRow) throw new HttpError(404, 'Setting not found', 'NOT_FOUND');
      const key = setting_key ?? settingRow.setting_key;
      settings.set(key, setting_value, req.user.id);
      audit.record(req.user.id, 'update', 'admin_moderation_and_settings', { setting_key: key, value: setting_value });
      res.json({ ok: true, setting_key: key, setting_value });
      return;
    }
    if (action === 'update_blocked_terms') {
      if (!Array.isArray(terms)) {
        throw new HttpError(400, 'terms must be an array', 'VALIDATION_ERROR');
      }
      settings.set('blocked_terms', terms, req.user.id);
      audit.record(req.user.id, 'update', 'admin_moderation_and_settings', { setting_key: 'blocked_terms' });
      res.json({ ok: true, setting_key: 'blocked_terms', setting_value: terms });
      return;
    }
    if (action === 'update_model_access') {
      if (!Array.isArray(terms)) {
        throw new HttpError(400, 'terms must be an array of model names', 'VALIDATION_ERROR');
      }
      settings.set('model_access', terms, req.user.id);
      audit.record(req.user.id, 'update', 'admin_moderation_and_settings', { setting_key: 'model_access' });
      res.json({ ok: true, setting_key: 'model_access', setting_value: terms });
      return;
    }
    throw new HttpError(400, 'action must be user_status, update_setting, update_blocked_terms, or update_model_access', 'VALIDATION_ERROR');
  });

  void MODEL_ACCESS_HELPER;
  void nowLog;
  return router;
}
