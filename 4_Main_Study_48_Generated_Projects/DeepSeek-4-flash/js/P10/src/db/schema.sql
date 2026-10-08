PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT    NOT NULL UNIQUE,
  email         TEXT    NOT NULL UNIQUE,
  password_hash TEXT    NOT NULL,
  role          TEXT    NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
  status        TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended')),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  token      TEXT    NOT NULL UNIQUE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT    NOT NULL DEFAULT (datetime('now')),
  expires_at TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS account_access (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  action     TEXT    NOT NULL CHECK (action IN ('register', 'login', 'logout', 'reset')),
  detail     TEXT,
  ip         TEXT,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS conversations (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title      TEXT    NOT NULL,
  status     TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived')),
  created_at TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS messages (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
  role            TEXT    NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
  content         TEXT    NOT NULL,
  citations       TEXT,
  created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS prompt_templates (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id) ON DELETE CASCADE,
  title      TEXT    NOT NULL,
  content    TEXT    NOT NULL,
  scope      TEXT    NOT NULL DEFAULT 'private' CHECK (scope IN ('private', 'shared')),
  is_public  INTEGER NOT NULL DEFAULT 0,
  created_by TEXT    NOT NULL DEFAULT 'user' CHECK (created_by IN ('user', 'admin')),
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS model_configurations (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  provider       TEXT    NOT NULL,
  model          TEXT    NOT NULL,
  temperature    REAL    NOT NULL DEFAULT 0.7,
  context_length INTEGER NOT NULL DEFAULT 4096,
  safety_mode    TEXT    NOT NULL DEFAULT 'balanced' CHECK (safety_mode IN ('strict', 'balanced', 'off')),
  is_default     INTEGER NOT NULL DEFAULT 0,
  created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS stored_files (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  original_name TEXT    NOT NULL,
  stored_name   TEXT    NOT NULL,
  path          TEXT    NOT NULL,
  size          INTEGER NOT NULL,
  mime_type     TEXT    NOT NULL,
  status        TEXT    NOT NULL DEFAULT 'ready' CHECK (status IN ('ready', 'processing', 'failed')),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS knowledge_files (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stored_file_id INTEGER NOT NULL REFERENCES stored_files(id) ON DELETE CASCADE,
  description    TEXT,
  content        TEXT    NOT NULL DEFAULT '',
  created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS retrieval_collections (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name        TEXT    NOT NULL,
  description TEXT,
  created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS collection_files (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_id     INTEGER NOT NULL REFERENCES retrieval_collections(id) ON DELETE CASCADE,
  knowledge_file_id INTEGER NOT NULL REFERENCES knowledge_files(id) ON DELETE CASCADE,
  UNIQUE (collection_id, knowledge_file_id)
);

CREATE TABLE IF NOT EXISTS tool_plugins (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT    NOT NULL UNIQUE,
  description TEXT,
  config      TEXT    NOT NULL DEFAULT '{}',
  enabled     INTEGER NOT NULL DEFAULT 1,
  created_by  INTEGER NOT NULL REFERENCES users(id),
  created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS user_tools (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tool_id INTEGER NOT NULL REFERENCES tool_plugins(id) ON DELETE CASCADE,
  enabled INTEGER NOT NULL DEFAULT 0,
  UNIQUE (user_id, tool_id)
);

CREATE TABLE IF NOT EXISTS api_keys (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  provider     TEXT    NOT NULL,
  key_prefix   TEXT    NOT NULL,
  masked_key   TEXT    NOT NULL,
  status       TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'revoked')),
  last_used_at TEXT,
  created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS chat_executions (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  conversation_id INTEGER REFERENCES conversations(id) ON DELETE SET NULL,
  prompt          TEXT    NOT NULL,
  response        TEXT    NOT NULL,
  citations       TEXT,
  status          TEXT    NOT NULL DEFAULT 'completed' CHECK (status IN ('completed', 'blocked', 'failed')),
  created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS share_conversations (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
  token           TEXT    NOT NULL UNIQUE,
  expires_at      TEXT,
  revoked         INTEGER NOT NULL DEFAULT 0,
  created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action     TEXT NOT NULL,
  module     TEXT NOT NULL,
  detail     TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS usage_logs (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  action      TEXT    NOT NULL,
  module      TEXT    NOT NULL,
  tokens_used INTEGER NOT NULL DEFAULT 0,
  detail      TEXT,
  created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admin_settings (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  setting_key   TEXT    NOT NULL UNIQUE,
  setting_value TEXT    NOT NULL,
  updated_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_chat_exec_user ON chat_executions(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_events(user_id);
CREATE INDEX IF NOT EXISTS idx_usage_user ON usage_logs(user_id);
