import crypto from 'node:crypto';
import { getDb } from './connection.js';
import { config } from '../config.js';

function hashPassword(password, salt) {
  const useSalt = salt || crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(password, useSalt, 32).toString('hex');
  return { salt: useSalt, hash };
}

function passwordRecord(password) {
  const { salt, hash } = hashPassword(password);
  return { password_hash: hash, password_salt: salt };
}

function nowIso() {
  return new Date().toISOString();
}

function futureIso(seconds) {
  return new Date(Date.now() + seconds * 1000).toISOString();
}

function id(prefix) {
  return `${prefix}_${crypto.randomBytes(6).toString('hex')}`;
}

function token(prefix, len = 24) {
  return `${prefix}_${crypto.randomBytes(len).toString('base64url')}`;
}

export function seedAll() {
  const db = getDb();
  const now = nowIso();

  db.transaction(() => {
    // --- Users ---
    const adminRecord = {
      id: id('usr'),
      email: 'admin@local.test',
      display_name: 'System Admin',
      role: 'admin',
      status: 'active',
      ...passwordRecord('Admin#12345')
    };
    const user1Record = {
      id: id('usr'),
      email: 'alice@local.test',
      display_name: 'Alice Researcher',
      role: 'user',
      status: 'active',
      ...passwordRecord('User#12345')
    };
    const user2Record = {
      id: id('usr'),
      email: 'bob@local.test',
      display_name: 'Bob Analyst',
      role: 'user',
      status: 'active',
      ...passwordRecord('User#12345')
    };

    const insertUser = db.prepare(`
      INSERT INTO users (id, email, display_name, password_hash, password_salt, role, status, created_at, updated_at)
      VALUES (@id, @email, @display_name, @password_hash, @password_salt, @role, @status, @created_at, @updated_at)
    `);
    insertUser.run({ ...adminRecord, created_at: now, updated_at: now });
    insertUser.run({ ...user1Record, created_at: now, updated_at: now });
    insertUser.run({ ...user2Record, created_at: now, updated_at: now });

    // --- Default model configuration per user ---
    const insertModel = db.prepare(`
      INSERT INTO model_configurations
        (id, owner_id, model_id, temperature, context_length, safety_mode, system_prompt, is_default, created_at, updated_at)
      VALUES (@id, @owner_id, @model_id, @temperature, @context_length, @safety_mode, @system_prompt, @is_default, @created_at, @updated_at)
    `);
    insertModel.run({
      id: id('mod'), owner_id: adminRecord.id, model_id: config.defaultModelId,
      temperature: config.defaultTemperature, context_length: config.defaultContextLength,
      safety_mode: 'standard', system_prompt: 'You are a helpful assistant.',
      is_default: 1, created_at: now, updated_at: now
    });
    insertModel.run({
      id: id('mod'), owner_id: user1Record.id, model_id: config.defaultModelId,
      temperature: 0.4, context_length: 4096,
      safety_mode: 'standard', system_prompt: 'You are Alice\'s research assistant.',
      is_default: 1, created_at: now, updated_at: now
    });
    insertModel.run({
      id: id('mod'), owner_id: user2Record.id, model_id: config.defaultModelId,
      temperature: 0.7, context_length: 2048,
      safety_mode: 'strict', system_prompt: 'You are Bob\'s analyst assistant.',
      is_default: 1, created_at: now, updated_at: now
    });

    // --- Conversations + messages ---
    const conv1 = {
      id: id('cnv'), owner_id: user1Record.id, title: 'Research notes on local models',
      model_id: config.defaultModelId, temperature: 0.4, context_length: 4096,
      safety_mode: 'standard', status: 'active', created_at: now, updated_at: now
    };
    const conv2 = {
      id: id('cnv'), owner_id: user2Record.id, title: 'Quarterly sales summary',
      model_id: config.defaultModelId, temperature: 0.7, context_length: 2048,
      safety_mode: 'strict', status: 'active', created_at: now, updated_at: now
    };
    db.prepare(`INSERT INTO conversations
      (id, owner_id, title, model_id, temperature, context_length, safety_mode, status, created_at, updated_at)
      VALUES (@id,@owner_id,@title,@model_id,@temperature,@context_length,@safety_mode,@status,@created_at,@updated_at)`)
      .run(conv1);
    db.prepare(`INSERT INTO conversations
      (id, owner_id, title, model_id, temperature, context_length, safety_mode, status, created_at, updated_at)
      VALUES (@id,@owner_id,@title,@model_id,@temperature,@context_length,@safety_mode,@status,@created_at,@updated_at)`)
      .run(conv2);

    const insertMsg = db.prepare(`INSERT INTO conversation_messages
      (id, conversation_id, role, content, citations, tokens_used, created_at)
      VALUES (@id,@conversation_id,@role,@content,@citations,@tokens_used,@created_at)`);
    insertMsg.run({
      id: id('msg'), conversation_id: conv1.id, role: 'user',
      content: 'What is a retrieval-augmented generation pipeline?',
      citations: null, tokens_used: 9, created_at: now
    });
    insertMsg.run({
      id: id('msg'), conversation_id: conv1.id, role: 'assistant',
      content: 'A retrieval-augmented generation pipeline combines an external retriever with a generative model.',
      citations: JSON.stringify([{ kind: 'definition', ref: 'kb://rag/intro' }]),
      tokens_used: 18, created_at: now
    });
    insertMsg.run({
      id: id('msg'), conversation_id: conv2.id, role: 'user',
      content: 'Summarize the sales trend for last quarter.',
      citations: null, tokens_used: 8, created_at: now
    });
    insertMsg.run({
      id: id('msg'), conversation_id: conv2.id, role: 'assistant',
      content: 'Sales rose 12% driven by the enterprise segment; SMB remained flat.',
      citations: null, tokens_used: 16, created_at: now
    });

    // --- Prompt templates ---
    const tpl1 = {
      id: id('tpl'), owner_id: user1Record.id, scope: 'user', name: 'Code reviewer',
      description: 'Review code for correctness and style.',
      body: 'Please review the following code:\n\n{{code}}',
      created_at: now, updated_at: now
    };
    const tpl2 = {
      id: id('tpl'), owner_id: null, scope: 'shared', name: 'Summarize meeting',
      description: 'Summarize a meeting transcript into action items.',
      body: 'Summarize the following transcript into bullets:\n\n{{transcript}}',
      created_at: now, updated_at: now
    };
    const tpl3 = {
      id: id('tpl'), owner_id: user2Record.id, scope: 'user', name: 'Sales brief',
      description: 'Compose a sales brief.',
      body: 'Compose a brief for: {{topic}}',
      created_at: now, updated_at: now
    };
    const insertTpl = db.prepare(`INSERT INTO prompt_templates
      (id, owner_id, scope, name, description, body, created_at, updated_at)
      VALUES (@id,@owner_id,@scope,@name,@description,@body,@created_at,@updated_at)`);
    insertTpl.run(tpl1);
    insertTpl.run(tpl2);
    insertTpl.run(tpl3);

    // --- Tool / plugin registry ---
    const plugins = [
      { identifier: 'retriever', name: 'Retriever',
        description: 'Search user-owned retrieval collections.',
        kind: 'retrieval', config_schema: JSON.stringify({ collection_id: 'string' }) },
      { identifier: 'calculator', name: 'Calculator',
        description: 'Evaluate arithmetic expressions.',
        kind: 'calculator', config_schema: JSON.stringify({ precision: 'number' }) },
      { identifier: 'translator', name: 'Translator',
        description: 'Translate short text to a target language.',
        kind: 'translator', config_schema: JSON.stringify({ target: 'string' }) },
      { identifier: 'time', name: 'Time',
        description: 'Return the current local time.',
        kind: 'time', config_schema: JSON.stringify({ timezone: 'string' }) }
    ];
    const insertPlugin = db.prepare(`INSERT INTO tool_plugins
      (id, identifier, name, description, kind, config_schema, global_enabled, created_at, updated_at)
      VALUES (@id,@identifier,@name,@description,@kind,@config_schema,1,@created_at,@updated_at)`);
    const pluginRows = plugins.map(p => ({
      id: id('plg'),
      identifier: p.identifier, name: p.name, description: p.description,
      kind: p.kind, config_schema: p.config_schema,
      created_at: now, updated_at: now
    }));
    for (const p of pluginRows) insertPlugin.run(p);

    const insertUserPlugin = db.prepare(`INSERT INTO user_tool_plugins
      (user_id, plugin_id, enabled, config_json, created_at, updated_at)
      VALUES (@user_id,@plugin_id,@enabled,@config_json,@created_at,@updated_at)`);
    insertUserPlugin.run({
      user_id: user1Record.id, plugin_id: pluginRows[0].id, enabled: 1,
      config_json: '{}', created_at: now, updated_at: now
    });
    insertUserPlugin.run({
      user_id: user2Record.id, plugin_id: pluginRows[1].id, enabled: 1,
      config_json: JSON.stringify({ precision: 4 }), created_at: now, updated_at: now
    });

    // --- API key seeds (masked) ---
    const insertApiKey = db.prepare(`INSERT INTO api_key_management
      (id, owner_id, label, provider, masked_key, secret_hash, secret_salt, last_rotated_at, created_at)
      VALUES (@id,@owner_id,@label,@provider,@masked_key,@secret_hash,@secret_salt,@last_rotated_at,@created_at)`);
    const sampleKey = 'sk-localbench-1A2B3C4D5E6F7G8H9I0J';
    const sampleRecord = passwordRecord(sampleKey);
    insertApiKey.run({
      id: id('key'), owner_id: user1Record.id, label: 'Default provider key',
      provider: 'openai-compatible',
      masked_key: 'sk-localbench-...9I0J',
      secret_hash: sampleRecord.password_hash,
      secret_salt: sampleRecord.password_salt,
      last_rotated_at: now, created_at: now
    });
    insertApiKey.run({
      id: id('key'), owner_id: adminRecord.id, label: 'Admin audit key',
      provider: 'internal',
      masked_key: 'sk-internal-...ADMIN',
      secret_hash: sampleRecord.password_hash,
      secret_salt: sampleRecord.password_salt,
      last_rotated_at: now, created_at: now
    });

    // --- Knowledge files (deterministic in-memory content) ---
    const file1 = {
      id: id('fil'), owner_id: user1Record.id, filename: 'rag-notes.txt',
      original_name: 'rag-notes.txt', mime_type: 'text/plain', size_bytes: 168,
      extension: '.txt', storage_path: 'seed://rag-notes.txt',
      checksum: 'seed-rag-notes', visibility: 'private', created_at: now
    };
    const file2 = {
      id: id('fil'), owner_id: user1Record.id, filename: 'meeting.md',
      original_name: 'meeting.md', mime_type: 'text/markdown', size_bytes: 122,
      extension: '.md', storage_path: 'seed://meeting.md',
      checksum: 'seed-meeting', visibility: 'private', created_at: now
    };
    db.prepare(`INSERT INTO stored_files
      (id, owner_id, filename, original_name, mime_type, size_bytes, extension, storage_path, checksum, visibility, created_at)
      VALUES (@id,@owner_id,@filename,@original_name,@mime_type,@size_bytes,@extension,@storage_path,@checksum,@visibility,@created_at)`)
      .run(file1);
    db.prepare(`INSERT INTO stored_files
      (id, owner_id, filename, original_name, mime_type, size_bytes, extension, storage_path, checksum, visibility, created_at)
      VALUES (@id,@owner_id,@filename,@original_name,@mime_type,@size_bytes,@extension,@storage_path,@checksum,@visibility,@created_at)`)
      .run(file2);

    // --- Retrieval collection containing file1 ---
    const coll = {
      id: id('col'), owner_id: user1Record.id, name: 'RAG notes',
      description: 'Notes on retrieval augmented generation.',
      created_at: now, updated_at: now
    };
    db.prepare(`INSERT INTO retrieval_collections
      (id, owner_id, name, description, created_at, updated_at)
      VALUES (@id,@owner_id,@name,@description,@created_at,@updated_at)`)
      .run(coll);
    db.prepare(`INSERT INTO retrieval_collection_files (collection_id, file_id) VALUES (?, ?)`)
      .run(coll.id, file1.id);
    const chunkInserts = [
      { content: 'Retrieval augmented generation pairs a retriever with a generator to ground responses.' },
      { content: 'Vector stores index documents by embedding similarity for fast nearest-neighbor lookup.' },
      { content: 'Citations can be returned to indicate the source documents that informed an answer.' }
    ];
    chunkInserts.forEach((c, idx) => {
      db.prepare(`INSERT INTO retrieval_chunks (id, file_id, collection_id, chunk_index, content)
        VALUES (?, ?, ?, ?, ?)`).run(id('chk'), file1.id, coll.id, idx, c.content);
    });

    // --- Share conversation (AI-10) ---
    db.prepare(`INSERT INTO share_conversations
      (id, conversation_id, owner_id, token, expires_at, view_count, created_at)
      VALUES (?,?,?,?,?,?,?)`).run(
        id('shr'), conv1.id, user1Record.id, token('shr', 16),
        futureIso(7 * 24 * 3600), 0, now
      );

    // --- Chat executions + usage records (AI-09, AI-11) ---
    const exec = {
      id: id('exe'), conversation_id: conv1.id, owner_id: user1Record.id,
      model_id: config.defaultModelId, prompt_tokens: 9, completion_tokens: 18,
      citations: JSON.stringify([{ kind: 'definition', ref: 'kb://rag/intro' }]),
      created_at: now
    };
    db.prepare(`INSERT INTO chat_executions
      (id, conversation_id, owner_id, model_id, prompt_tokens, completion_tokens, citations, created_at)
      VALUES (@id,@conversation_id,@owner_id,@model_id,@prompt_tokens,@completion_tokens,@citations,@created_at)`)
      .run(exec);
    db.prepare(`INSERT INTO usage_records
      (id, user_id, conversation_id, model_id, prompt_tokens, completion_tokens, created_at)
      VALUES (?,?,?,?,?,?,?)`).run(
        id('use'), user1Record.id, conv1.id, config.defaultModelId, 9, 18, now
      );

    // --- Audit events (AI-11, AI-12) ---
    const insertAudit = db.prepare(`INSERT INTO audit_events
      (id, actor_id, actor_role, action, target_kind, target_id, outcome, details, created_at)
      VALUES (?,?,?,?,?,?,?,?,?)`);
    insertAudit.run(id('aud'), adminRecord.id, 'admin', 'seed.bootstrap', 'system', null, 'info',
      'Initial seed completed', now);
    insertAudit.run(id('aud'), user1Record.id, 'user', 'chat.execute', 'conversation', conv1.id, 'success',
      'RAG intro question', now);

    // --- Admin moderation seed (AI-12) ---
    db.prepare(`INSERT INTO admin_settings (key, value, updated_by, updated_at) VALUES (?, ?, ?, ?)`)
      .run('default_safety_mode', 'standard', adminRecord.id, now);
    db.prepare(`INSERT INTO admin_settings (key, value, updated_by, updated_at) VALUES (?, ?, ?, ?)`)
      .run('max_context_length', '8192', adminRecord.id, now);
    db.prepare(`INSERT INTO admin_settings (key, value, updated_by, updated_at) VALUES (?, ?, ?, ?)`)
      .run('allow_user_models', '1', adminRecord.id, now);

    db.prepare(`INSERT INTO blocked_terms (id, term, severity, created_by, created_at)
      VALUES (?, ?, ?, ?, ?)`).run(id('blk'), 'forbidden-advice', 'block', adminRecord.id, now);
    db.prepare(`INSERT INTO blocked_terms (id, term, severity, created_by, created_at)
      VALUES (?, ?, ?, ?, ?)`).run(id('blk'), 'risky-claim', 'warn', adminRecord.id, now);

    db.prepare(`INSERT INTO user_moderation (id, user_id, decision, reason, actor_id, created_at)
      VALUES (?, ?, ?, ?, ?, ?)`).run(
        id('mod'), user2Record.id, 'reinstate', 'Reset baseline after onboarding',
        adminRecord.id, now
      );
  })();
}

export function resetAndSeed() {
  const db = getDb();
  const tables = db.prepare(`SELECT name FROM sqlite_master WHERE type='table'`).all();
  db.transaction(() => {
    for (const t of tables) {
      if (t.name.startsWith('sqlite_')) continue;
      db.exec(`DROP TABLE IF EXISTS "${t.name}"`);
    }
  })();
}
