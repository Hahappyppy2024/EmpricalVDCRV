# netd — schema reference SQL.
# The daemon initializes the schema at runtime using sqlite3_exec with the
# statements below. This file is kept for reference and offline inspection.

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA busy_timeout = 2000;

CREATE TABLE IF NOT EXISTS schema_version (
    version INTEGER PRIMARY KEY,
    applied_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS principals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL CHECK (role IN ('client','monitoring','administrator')),
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS access_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    principal_id INTEGER NOT NULL REFERENCES principals(id),
    token_hash TEXT NOT NULL UNIQUE,
    token_prefix TEXT NOT NULL,
    role TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
        CHECK (status IN ('active','revoked','expired')),
    expires_at INTEGER,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS namespace_grants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    principal_id INTEGER NOT NULL REFERENCES principals(id),
    namespace TEXT NOT NULL,
    permission TEXT NOT NULL CHECK (permission IN ('read','write','admin')),
    UNIQUE (principal_id, namespace)
);

CREATE TABLE IF NOT EXISTS records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    namespace TEXT NOT NULL,
    key TEXT NOT NULL,
    value BLOB NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    expires_at INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE (namespace, key)
);

CREATE INDEX IF NOT EXISTS idx_records_ns_key ON records(namespace, key);
CREATE INDEX IF NOT EXISTS idx_records_expires ON records(expires_at);

CREATE TABLE IF NOT EXISTS service_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at INTEGER NOT NULL,
    ended_at INTEGER,
    pid INTEGER NOT NULL,
    generation INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS configuration_generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    generation INTEGER NOT NULL UNIQUE,
    applied_at INTEGER NOT NULL,
    source TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    outcome TEXT NOT NULL,
    connection_id TEXT,
    principal_id INTEGER,
    namespace TEXT,
    key TEXT,
    details TEXT
);

CREATE INDEX IF NOT EXISTS idx_audit_ts ON audit_events(ts);

CREATE TABLE IF NOT EXISTS service_metrics (
    name TEXT PRIMARY KEY,
    value INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL
);