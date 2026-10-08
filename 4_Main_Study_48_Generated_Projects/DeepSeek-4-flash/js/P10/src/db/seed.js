import fs from 'node:fs';
import path from 'node:path';
import bcrypt from 'bcryptjs';
import { config, ROOT_DIR } from '../config.js';
import { nowIso } from './database.js';

const FIXED_TS = '2026-08-01T10:00:00.000Z';

function hashPassword(pw) {
  return bcrypt.hashSync(pw, 10);
}

function clear(db) {
  const tables = [
    'admin_settings',
    'usage_logs',
    'audit_events',
    'share_conversations',
    'chat_executions',
    'api_keys',
    'user_tools',
    'tool_plugins',
    'collection_files',
    'retrieval_collections',
    'knowledge_files',
    'stored_files',
    'model_configurations',
    'prompt_templates',
    'messages',
    'conversations',
    'account_access',
    'sessions',
    'users',
  ];
  const clearAll = db.transaction(() => {
    for (const table of tables) {
      db.prepare(`DELETE FROM ${table}`).run();
    }
  });
  clearAll();
  for (const table of tables) {
    db.prepare(`DELETE FROM sqlite_sequence WHERE name = ?`).run(table);
  }
}

function writeUpload(originalName, content, id) {
  const storedName = `seed_kf_${id}_${originalName.replace(/[^a-zA-Z0-9._-]/g, '_')}`;
  const target = path.join(config.uploadDir, storedName);
  fs.mkdirSync(config.uploadDir, { recursive: true });
  fs.writeFileSync(target, content, 'utf8');
  return { storedName, target };
}

export function seedDatabase(db, { force = false } = {}) {
  const already = db.prepare('SELECT id FROM users WHERE username = ?').get('admin');
  if (already && !force) {
    return { seeded: false, reason: 'already-seeded' };
  }

  if (force) {
    clear(db);
  }

  const tx = db.transaction(() => {
    const now = nowIso();

    const insertUser = db.prepare(
      `INSERT INTO users (id, username, email, password_hash, role, status, created_at)
       VALUES (@id, @username, @email, @password_hash, @role, @status, @created_at)`
    );
    const adminId = 1;
    const aliceId = 2;
    const bobId = 3;
    insertUser.run({
      id: adminId, username: 'admin', email: 'admin@example.test',
      password_hash: hashPassword('admin123'), role: 'admin', status: 'active', created_at: FIXED_TS,
    });
    insertUser.run({
      id: aliceId, username: 'alice', email: 'alice@example.test',
      password_hash: hashPassword('alice123'), role: 'user', status: 'active', created_at: FIXED_TS,
    });
    insertUser.run({
      id: bobId, username: 'bob', email: 'bob@example.test',
      password_hash: hashPassword('bob123'), role: 'user', status: 'active', created_at: FIXED_TS,
    });

    db.prepare(
      `INSERT INTO sessions (id, token, user_id, created_at, expires_at) VALUES (1, ?, ?, ?, ?)`
    ).run('seed-session-alice', aliceId, FIXED_TS, '2030-01-01T00:00:00.000Z');

    const insertAccess = db.prepare(
      `INSERT INTO account_access (id, user_id, action, detail, ip, created_at)
       VALUES (@id, @user_id, @action, @detail, @ip, @created_at)`
    );
    insertAccess.run({ id: 1, user_id: adminId, action: 'register', detail: 'seed account creation', ip: '127.0.0.1', created_at: FIXED_TS });
    insertAccess.run({ id: 2, user_id: aliceId, action: 'register', detail: 'seed account creation', ip: '127.0.0.1', created_at: FIXED_TS });
    insertAccess.run({ id: 3, user_id: aliceId, action: 'login', detail: 'seeded session', ip: '127.0.0.1', created_at: FIXED_TS });
    insertAccess.run({ id: 4, user_id: bobId, action: 'register', detail: 'seed account creation', ip: '127.0.0.1', created_at: FIXED_TS });

    const insertConversation = db.prepare(
      `INSERT INTO conversations (id, user_id, title, status, created_at, updated_at)
       VALUES (@id, @user_id, @title, @status, @created_at, @updated_at)`
    );
    insertConversation.run({ id: 1, user_id: aliceId, title: 'Product launch planning', status: 'active', created_at: FIXED_TS, updated_at: FIXED_TS });
    insertConversation.run({ id: 2, user_id: aliceId, title: 'Research: vector databases', status: 'active', created_at: FIXED_TS, updated_at: FIXED_TS });
    insertConversation.run({ id: 3, user_id: aliceId, title: 'Old project notes', status: 'archived', created_at: FIXED_TS, updated_at: FIXED_TS });
    insertConversation.run({ id: 4, user_id: bobId, title: 'Onboarding checklist', status: 'active', created_at: FIXED_TS, updated_at: FIXED_TS });

    const insertMessage = db.prepare(
      `INSERT INTO messages (id, conversation_id, role, content, citations, created_at)
       VALUES (@id, @conversation_id, @role, @content, @citations, @created_at)`
    );
    insertMessage.run({ id: 1, conversation_id: 1, role: 'user', content: 'Draft a timeline for the product launch.', citations: null, created_at: FIXED_TS });
    insertMessage.run({ id: 2, conversation_id: 1, role: 'assistant', content: 'Here is a four-week launch timeline covering beta, QA, release, and marketing.', citations: null, created_at: FIXED_TS });
    insertMessage.run({ id: 3, conversation_id: 2, role: 'user', content: 'Compare vector databases for our RAG stack.', citations: null, created_at: FIXED_TS });
    insertMessage.run({ id: 4, conversation_id: 2, role: 'assistant', content: 'Milvus and Qdrant are strong options; see the knowledge files in your retrieval collections for benchmarks.', citations: JSON.stringify([{ file: 'vector-db-notes.md', snippet: 'Milvus performs best on large-scale ANN benchmarks.' }]), created_at: FIXED_TS });
    insertMessage.run({ id: 5, conversation_id: 3, role: 'user', content: 'Summarize the archived project notes.', citations: null, created_at: FIXED_TS });
    insertMessage.run({ id: 6, conversation_id: 4, role: 'user', content: 'Create an onboarding checklist for new engineers.', citations: null, created_at: FIXED_TS });
    insertMessage.run({ id: 7, conversation_id: 4, role: 'assistant', content: 'Onboarding covers environment setup, repo access, code review rules, and first-week goals.', citations: null, created_at: FIXED_TS });

    const insertTemplate = db.prepare(
      `INSERT INTO prompt_templates (id, user_id, title, content, scope, is_public, created_by, created_at)
       VALUES (@id, @user_id, @title, @content, @scope, @is_public, @created_by, @created_at)`
    );
    insertTemplate.run({ id: 1, user_id: aliceId, title: 'Code review helper', content: 'Review the following code for correctness, performance, and readability:\n\n{{CODE}}', scope: 'private', is_public: 0, created_by: 'user', created_at: FIXED_TS });
    insertTemplate.run({ id: 2, user_id: adminId, title: 'Meeting summary', content: 'Summarize the meeting notes into action items, owners, and deadlines:\n\n{{NOTES}}', scope: 'shared', is_public: 1, created_by: 'admin', created_at: FIXED_TS });
    insertTemplate.run({ id: 3, user_id: adminId, title: 'RAG system design', content: 'Design a retrieval-augmented generation system with collection, chunking, embedding, and ranking steps.', scope: 'shared', is_public: 1, created_by: 'admin', created_at: FIXED_TS });
    insertTemplate.run({ id: 4, user_id: bobId, title: 'Bug triage', content: 'Triage the reported bug by severity, affected module, and suggested fix.\n\n{{BUG_REPORT}}', scope: 'private', is_public: 0, created_by: 'user', created_at: FIXED_TS });

    const insertModel = db.prepare(
      `INSERT INTO model_configurations (id, user_id, provider, model, temperature, context_length, safety_mode, is_default, created_at)
       VALUES (@id, @user_id, @provider, @model, @temperature, @context_length, @safety_mode, @is_default, @created_at)`
    );
    insertModel.run({ id: 1, user_id: aliceId, provider: 'openai', model: 'gpt-4o-mini', temperature: 0.7, context_length: 8192, safety_mode: 'balanced', is_default: 1, created_at: FIXED_TS });
    insertModel.run({ id: 2, user_id: aliceId, provider: 'anthropic', model: 'claude-3-5-sonnet', temperature: 0.4, context_length: 16384, safety_mode: 'strict', is_default: 0, created_at: FIXED_TS });
    insertModel.run({ id: 3, user_id: bobId, provider: 'openai', model: 'gpt-4o-mini', temperature: 0.8, context_length: 4096, safety_mode: 'off', is_default: 1, created_at: FIXED_TS });

    const seedFiles = [
      { id: 1, userId: aliceId, name: 'vector-db-notes.md', content: 'Vector database benchmark notes.\nMilvus performs best on large-scale ANN benchmarks.\nQdrant offers efficient filtering.\nChroma is simple for prototyping.\nWeights of these findings come from the 2025 ANN survey.' },
      { id: 2, userId: aliceId, name: 'launch-checklist.txt', content: 'Product launch checklist.\n1. Freeze features\n2. Run QA regression\n3. Prepare release notes\n4. Schedule marketing email\n5. Monitor metrics post launch' },
      { id: 3, userId: bobId, name: 'onboarding.txt', content: 'Engineer onboarding.\nSet up local environment.\nClone the repository.\nRead code review guidelines.\nComplete first week goals.' },
    ];

    const insertStored = db.prepare(
      `INSERT INTO stored_files (id, user_id, original_name, stored_name, path, size, mime_type, status, created_at)
       VALUES (@id, @user_id, @original_name, @stored_name, @path, @size, @mime_type, @status, @created_at)`
    );
    const insertKnowledge = db.prepare(
      `INSERT INTO knowledge_files (id, user_id, stored_file_id, description, content, created_at)
       VALUES (@id, @user_id, @stored_file_id, @description, @content, @created_at)`
    );
    let kfId = 1;
    for (const f of seedFiles) {
      const { storedName, target } = writeUpload(f.name, f.content, f.id);
      insertStored.run({
        id: f.id, user_id: f.userId, original_name: f.name, stored_name: storedName,
        path: target, size: Buffer.byteLength(f.content), mime_type: f.name.endsWith('.md') ? 'text/markdown' : 'text/plain',
        status: 'ready', created_at: FIXED_TS,
      });
      insertKnowledge.run({
        id: kfId++, user_id: f.userId, stored_file_id: f.id,
        description: `Seeded knowledge file: ${f.name}`, content: f.content, created_at: FIXED_TS,
      });
    }

    const insertCollection = db.prepare(
      `INSERT INTO retrieval_collections (id, user_id, name, description, created_at)
       VALUES (@id, @user_id, @name, @description, @created_at)`
    );
    insertCollection.run({ id: 1, user_id: aliceId, name: 'RAG research', description: 'Notes on vector databases and retrieval.', created_at: FIXED_TS });
    insertCollection.run({ id: 2, user_id: aliceId, name: 'Product launch', description: 'Launch planning checklist.', created_at: FIXED_TS });
    insertCollection.run({ id: 3, user_id: bobId, name: 'Engineering onboarding', description: 'Onboarding material for engineers.', created_at: FIXED_TS });

    const insertCollectionFile = db.prepare(
      `INSERT INTO collection_files (id, collection_id, knowledge_file_id) VALUES (?, ?, ?)`
    );
    insertCollectionFile.run(1, 1, 1);
    insertCollectionFile.run(2, 2, 2);
    insertCollectionFile.run(3, 3, 3);

    const insertTool = db.prepare(
      `INSERT INTO tool_plugins (id, name, description, config, enabled, created_by, created_at)
       VALUES (@id, @name, @description, @config, @enabled, @created_by, @created_at)`
    );
    insertTool.run({ id: 1, name: 'web_search', description: 'Search the local knowledge index and return top matches.', config: JSON.stringify({ max_results: 3 }), enabled: 1, created_by: adminId, created_at: FIXED_TS });
    insertTool.run({ id: 2, name: 'calculator', description: 'Evaluate deterministic arithmetic expressions.', config: JSON.stringify({ precision: 6 }), enabled: 1, created_by: adminId, created_at: FIXED_TS });
    insertTool.run({ id: 3, name: 'image_generator', description: 'Deterministic placeholder image reference generator.', config: JSON.stringify({ placeholder_host: 'https://picsum.photos/seed/' }), enabled: 1, created_by: adminId, created_at: FIXED_TS });
    insertTool.run({ id: 4, name: 'translator', description: 'Deterministic dictionary-style translator (en <-> fr samples).', config: JSON.stringify({ pairs: 10 }), enabled: 0, created_by: adminId, created_at: FIXED_TS });

    const insertUserTool = db.prepare(
      `INSERT INTO user_tools (id, user_id, tool_id, enabled) VALUES (?, ?, ?, ?)`
    );
    insertUserTool.run(1, aliceId, 1, 1);
    insertUserTool.run(2, aliceId, 2, 1);
    insertUserTool.run(3, aliceId, 3, 0);
    insertUserTool.run(4, bobId, 1, 1);
    insertUserTool.run(5, bobId, 2, 0);

    const insertApiKey = db.prepare(
      `INSERT INTO api_keys (id, user_id, provider, key_prefix, masked_key, status, last_used_at, created_at)
       VALUES (@id, @user_id, @provider, @key_prefix, @masked_key, @status, @last_used_at, @created_at)`
    );
    insertApiKey.run({ id: 1, user_id: aliceId, provider: 'openai', key_prefix: 'sk-alice', masked_key: 'sk-****...abcd', status: 'active', last_used_at: FIXED_TS, created_at: FIXED_TS });
    insertApiKey.run({ id: 2, user_id: aliceId, provider: 'anthropic', key_prefix: 'sk-ant-alice', masked_key: 'sk-ant-****...wxyz', status: 'active', last_used_at: null, created_at: FIXED_TS });
    insertApiKey.run({ id: 3, user_id: bobId, provider: 'openai', key_prefix: 'sk-bob', masked_key: 'sk-****...0001', status: 'revoked', last_used_at: FIXED_TS, created_at: FIXED_TS });

    const insertChat = db.prepare(
      `INSERT INTO chat_executions (id, user_id, conversation_id, prompt, response, citations, status, created_at)
       VALUES (@id, @user_id, @conversation_id, @prompt, @response, @citations, @status, @created_at)`
    );
    insertChat.run({ id: 1, user_id: aliceId, conversation_id: 1, prompt: 'Draft a timeline for the product launch.', response: 'Proposed four-week timeline: beta (W1), QA (W2), release candidate (W3), launch and marketing (W4).', citations: null, status: 'completed', created_at: FIXED_TS });
    insertChat.run({ id: 2, user_id: aliceId, conversation_id: 2, prompt: 'Compare vector databases for our RAG stack.', response: 'Milvus and Qdrant are strong options; see the knowledge files in your retrieval collections for benchmarks.', citations: JSON.stringify([{ file: 'vector-db-notes.md', snippet: 'Milvus performs best on large-scale ANN benchmarks.' }]), status: 'completed', created_at: FIXED_TS });
    insertChat.run({ id: 3, user_id: bobId, conversation_id: 4, prompt: 'Create an onboarding checklist.', response: 'Onboarding covers environment setup, repo access, code review rules, and first-week goals.', citations: null, status: 'completed', created_at: FIXED_TS });

    const insertShare = db.prepare(
      `INSERT INTO share_conversations (id, user_id, conversation_id, token, expires_at, revoked, created_at)
       VALUES (@id, @user_id, @conversation_id, @token, @expires_at, @revoked, @created_at)`
    );
    insertShare.run({ id: 1, user_id: aliceId, conversation_id: 1, token: 'share-prod-launch-3f7a', expires_at: null, revoked: 0, created_at: FIXED_TS });
    insertShare.run({ id: 2, user_id: aliceId, conversation_id: 3, token: 'share-old-notes-91bc', expires_at: '2026-06-01T00:00:00.000Z', revoked: 1, created_at: FIXED_TS });

    const insertAudit = db.prepare(
      `INSERT INTO audit_events (id, user_id, action, module, detail, created_at)
       VALUES (@id, @user_id, @action, @module, @detail, @created_at)`
    );
    insertAudit.run({ id: 1, user_id: adminId, action: 'create', module: 'tool_plugin_registry', detail: JSON.stringify({ tool: 'web_search' }), created_at: FIXED_TS });
    insertAudit.run({ id: 2, user_id: adminId, action: 'update', module: 'admin_moderation_and_settings', detail: JSON.stringify({ setting: 'default_model', value: 'gpt-4o-mini' }), created_at: FIXED_TS });
    insertAudit.run({ id: 3, user_id: aliceId, action: 'create', module: 'api_key_management', detail: JSON.stringify({ provider: 'openai', prefix: 'sk-alice' }), created_at: FIXED_TS });
    insertAudit.run({ id: 4, user_id: aliceId, action: 'archive', module: 'conversation_management', detail: JSON.stringify({ conversation_id: 3 }), created_at: FIXED_TS });
    insertAudit.run({ id: 5, user_id: adminId, action: 'update', module: 'model_configuration', detail: JSON.stringify({ user: 'bob', config: 'gpt-4o-mini' }), created_at: FIXED_TS });
    insertAudit.run({ id: 6, user_id: bobId, action: 'create', module: 'prompt_templates', detail: JSON.stringify({ template: 'Bug triage' }), created_at: FIXED_TS });

    const insertUsage = db.prepare(
      `INSERT INTO usage_logs (id, user_id, action, module, tokens_used, detail, created_at)
       VALUES (@id, @user_id, @action, @module, @tokens_used, @detail, @created_at)`
    );
    insertUsage.run({ id: 1, user_id: aliceId, action: 'chat', module: 'chat_execution', tokens_used: 512, detail: 'Product launch timeline prompt', created_at: FIXED_TS });
    insertUsage.run({ id: 2, user_id: aliceId, action: 'chat', module: 'chat_execution', tokens_used: 780, detail: 'Vector DB comparison prompt', created_at: FIXED_TS });
    insertUsage.run({ id: 3, user_id: aliceId, action: 'retrieve', module: 'retrieval_collection', tokens_used: 0, detail: 'search: vector database', created_at: FIXED_TS });
    insertUsage.run({ id: 4, user_id: bobId, action: 'chat', module: 'chat_execution', tokens_used: 320, detail: 'Onboarding checklist prompt', created_at: FIXED_TS });
    insertUsage.run({ id: 5, user_id: bobId, action: 'upload', module: 'knowledge_file_upload', tokens_used: 0, detail: 'onboarding.txt', created_at: FIXED_TS });

    const insertSetting = db.prepare(
      `INSERT INTO admin_settings (id, setting_key, setting_value, updated_by, updated_at)
       VALUES (@id, @setting_key, @setting_value, @updated_by, @updated_at)`
    );
    insertSetting.run({ id: 1, setting_key: 'blocked_terms', setting_value: JSON.stringify(['ignore previous instructions', 'exfiltrate', 'reveal system prompt']), updated_by: adminId, updated_at: FIXED_TS });
    insertSetting.run({ id: 2, setting_key: 'default_model', setting_value: JSON.stringify('gpt-4o-mini'), updated_by: adminId, updated_at: FIXED_TS });
    insertSetting.run({ id: 3, setting_key: 'default_provider', setting_value: JSON.stringify('openai'), updated_by: adminId, updated_at: FIXED_TS });
    insertSetting.run({ id: 4, setting_key: 'allow_registration', setting_value: JSON.stringify(true), updated_by: adminId, updated_at: FIXED_TS });
    insertSetting.run({ id: 5, setting_key: 'model_access', setting_value: JSON.stringify(['gpt-4o-mini', 'gpt-4o', 'claude-3-5-sonnet']), updated_by: adminId, updated_at: FIXED_TS });
    insertSetting.run({ id: 6, setting_key: 'max_upload_size', setting_value: JSON.stringify(5 * 1024 * 1024), updated_by: adminId, updated_at: FIXED_TS });

    void now;
  });

  tx();
  return { seeded: true, force };
}

export function seedIfEmpty(db) {
  return seedDatabase(db, { force: false });
}
