import { getDb } from './connection.js';

const SCHEMA_VERSION = 1;

const SCHEMA_STATEMENTS = [
  // --- Core identity ---
  `CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    password_salt TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user','admin')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended','disabled')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )`,

  // --- Sessions (AI-01) ---
  `CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id)`,
  `CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at)`,

  // --- Account access records (AI-01) ---
  `CREATE TABLE IF NOT EXISTS account_access (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    action TEXT NOT NULL CHECK (action IN ('sign_in','sign_out','register','reset')),
    ip_address TEXT,
    user_agent TEXT,
    outcome TEXT NOT NULL CHECK (outcome IN ('success','failure')),
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  )`,

  // --- Conversations (AI-02) ---
  `CREATE TABLE IF NOT EXISTS conversations (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL,
    title TEXT NOT NULL,
    model_id TEXT NOT NULL,
    temperature REAL NOT NULL,
    context_length INTEGER NOT NULL,
    safety_mode TEXT NOT NULL DEFAULT 'standard' CHECK (safety_mode IN ('standard','strict','off')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived','deleted')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_conv_owner ON conversations(owner_id)`,
  `CREATE INDEX IF NOT EXISTS idx_conv_status ON conversations(status)`,

  `CREATE TABLE IF NOT EXISTS conversation_messages (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user','assistant','system')),
    content TEXT NOT NULL,
    citations TEXT,
    tokens_used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_msg_conv ON conversation_messages(conversation_id)`,

  // --- Prompt templates (AI-03) ---
  `CREATE TABLE IF NOT EXISTS prompt_templates (
    id TEXT PRIMARY KEY,
    owner_id TEXT,
    scope TEXT NOT NULL CHECK (scope IN ('user','shared')),
    name TEXT NOT NULL,
    description TEXT,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
  )`,
  `CREATE UNIQUE INDEX IF NOT EXISTS idx_tpl_owner_name
    ON prompt_templates(COALESCE(owner_id, ''), name, scope)`,

  // --- Model configuration (AI-04) ---
  `CREATE TABLE IF NOT EXISTS model_configurations (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL,
    model_id TEXT NOT NULL,
    temperature REAL NOT NULL,
    context_length INTEGER NOT NULL,
    safety_mode TEXT NOT NULL CHECK (safety_mode IN ('standard','strict','off')),
    system_prompt TEXT,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE UNIQUE INDEX IF NOT EXISTS idx_model_owner_default
    ON model_configurations(owner_id) WHERE is_default = 1`,

  // --- Knowledge files (AI-05) ---
  `CREATE TABLE IF NOT EXISTS stored_files (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size_bytes INTEGER NOT NULL,
    extension TEXT NOT NULL,
    storage_path TEXT NOT NULL,
    checksum TEXT NOT NULL,
    visibility TEXT NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','shared')),
    created_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_files_owner ON stored_files(owner_id)`,

  // --- Retrieval collections (AI-06) ---
  `CREATE TABLE IF NOT EXISTS retrieval_collections (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE UNIQUE INDEX IF NOT EXISTS idx_collection_owner_name
    ON retrieval_collections(owner_id, name)`,

  `CREATE TABLE IF NOT EXISTS retrieval_collection_files (
    collection_id TEXT NOT NULL,
    file_id TEXT NOT NULL,
    PRIMARY KEY (collection_id, file_id),
    FOREIGN KEY (collection_id) REFERENCES retrieval_collections(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES stored_files(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_rcf_file ON retrieval_collection_files(file_id)`,

  `CREATE TABLE IF NOT EXISTS retrieval_chunks (
    id TEXT PRIMARY KEY,
    file_id TEXT NOT NULL,
    collection_id TEXT NOT NULL,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    FOREIGN KEY (file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    FOREIGN KEY (collection_id) REFERENCES retrieval_collections(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_chunks_collection ON retrieval_chunks(collection_id)`,

  // --- Tool/plugin registry (AI-07) ---
  `CREATE TABLE IF NOT EXISTS tool_plugins (
    id TEXT PRIMARY KEY,
    identifier TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    kind TEXT NOT NULL CHECK (kind IN ('retrieval','webhook','calculator','translator','time')),
    config_schema TEXT NOT NULL,
    global_enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )`,
  `CREATE TABLE IF NOT EXISTS user_tool_plugins (
    user_id TEXT NOT NULL,
    plugin_id TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 0,
    config_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    PRIMARY KEY (user_id, plugin_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plugin_id) REFERENCES tool_plugins(id) ON DELETE CASCADE
  )`,

  // --- API keys (AI-08) ---
  `CREATE TABLE IF NOT EXISTS api_key_management (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL,
    label TEXT NOT NULL,
    provider TEXT NOT NULL,
    masked_key TEXT NOT NULL,
    secret_hash TEXT NOT NULL,
    secret_salt TEXT NOT NULL,
    last_rotated_at TEXT NOT NULL,
    revoked_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,
  `CREATE INDEX IF NOT EXISTS idx_apikey_owner ON api_key_management(owner_id)`,

  // --- Chat execution (AI-09) ---
  `CREATE TABLE IF NOT EXISTS chat_executions (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    owner_id TEXT NOT NULL,
    model_id TEXT NOT NULL,
    prompt_tokens INTEGER NOT NULL DEFAULT 0,
    completion_tokens INTEGER NOT NULL DEFAULT 0,
    citations TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,

  // --- Share conversation (AI-10) ---
  `CREATE TABLE IF NOT EXISTS share_conversations (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    owner_id TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT,
    revoked_at TEXT,
    view_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
  )`,

  // --- Usage / audit logs (AI-11) ---
  `CREATE TABLE IF NOT EXISTS audit_events (
    id TEXT PRIMARY KEY,
    actor_id TEXT,
    actor_role TEXT,
    action TEXT NOT NULL,
    target_kind TEXT,
    target_id TEXT,
    outcome TEXT NOT NULL CHECK (outcome IN ('success','failure','info')),
    details TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
  )`,
  `CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_events(actor_id)`,
  `CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_events(created_at)`,

  `CREATE TABLE IF NOT EXISTS usage_records (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    conversation_id TEXT,
    model_id TEXT NOT NULL,
    prompt_tokens INTEGER NOT NULL DEFAULT 0,
    completion_tokens INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL
  )`,
  `CREATE INDEX IF NOT EXISTS idx_usage_user ON usage_records(user_id)`,

  // --- Admin moderation (AI-12) ---
  `CREATE TABLE IF NOT EXISTS admin_settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_by TEXT,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
  )`,
  `CREATE TABLE IF NOT EXISTS blocked_terms (
    id TEXT PRIMARY KEY,
    term TEXT NOT NULL UNIQUE,
    severity TEXT NOT NULL CHECK (severity in ('warn','block')),
    created_by TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
  )`,
  `CREATE TABLE IF NOT EXISTS user_moderation (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    decision TEXT NOT NULL CHECK (decision IN ('suspend','reinstate','disable','reinstate_disabled')),
    reason TEXT NOT NULL,
    actor_id TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
  )`,

  // --- Schema metadata ---
  `CREATE TABLE IF NOT EXISTS schema_meta (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
  )`
];

export function applySchema() {
  const db = getDb();
  db.transaction(() => {
    for (const stmt of SCHEMA_STATEMENTS) db.exec(stmt);
    db.prepare(`INSERT OR REPLACE INTO schema_meta(key, value) VALUES (?, ?)`)
      .run('schema_version', String(SCHEMA_VERSION));
  })();
}

export function currentSchemaVersion() {
  const db = getDb();
  const row = db.prepare(`SELECT value FROM schema_meta WHERE key = 'schema_version'`).get();
  return row ? parseInt(row.value, 10) : 0;
}
