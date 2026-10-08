PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    username       TEXT NOT NULL UNIQUE,
    email          TEXT NOT NULL UNIQUE,
    password_hash  TEXT NOT NULL,
    full_name      TEXT NOT NULL,
    role           TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
    quota_bytes    INTEGER NOT NULL DEFAULT 104857600,
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token         TEXT NOT NULL UNIQUE,
    ip_address    TEXT,
    user_agent    TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at    TEXT NOT NULL,
    last_activity TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

CREATE TABLE IF NOT EXISTS account_access (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    username    TEXT NOT NULL,
    action      TEXT NOT NULL CHECK (action IN ('register','login','login_failed','logout','reset_request','reset')),
    status      TEXT NOT NULL DEFAULT 'success' CHECK (status IN ('success','failure')),
    ip_address  TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_account_access_user ON account_access(user_id);

CREATE TABLE IF NOT EXISTS folders (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id  INTEGER REFERENCES folders(id) ON DELETE CASCADE,
    name       TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (owner_id, parent_id, name)
);
CREATE INDEX IF NOT EXISTS idx_folders_owner ON folders(owner_id);
CREATE INDEX IF NOT EXISTS idx_folders_parent ON folders(parent_id);

CREATE TABLE IF NOT EXISTS files (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    folder_id       INTEGER REFERENCES folders(id) ON DELETE SET NULL,
    name            TEXT NOT NULL,
    original_name   TEXT NOT NULL,
    storage_path    TEXT NOT NULL,
    mime_type       TEXT NOT NULL,
    size_bytes      INTEGER NOT NULL,
    description     TEXT NOT NULL DEFAULT '',
    tags            TEXT NOT NULL DEFAULT '',
    status          TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','trashed')),
    current_version INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    trashed_at      TEXT,
    deleted_at      TEXT
);
CREATE INDEX IF NOT EXISTS idx_files_owner ON files(owner_id);
CREATE INDEX IF NOT EXISTS idx_files_folder ON files(folder_id);
CREATE INDEX IF NOT EXISTS idx_files_status ON files(status);
CREATE INDEX IF NOT EXISTS idx_files_name ON files(name);

CREATE TABLE IF NOT EXISTS file_versions (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id        INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    version_number INTEGER NOT NULL,
    storage_path   TEXT NOT NULL,
    size_bytes     INTEGER NOT NULL,
    mime_type      TEXT NOT NULL,
    uploaded_by    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    comment        TEXT NOT NULL DEFAULT '',
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (file_id, version_number)
);
CREATE INDEX IF NOT EXISTS idx_versions_file ON file_versions(file_id);

CREATE TABLE IF NOT EXISTS shares (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id        INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    owner_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token          TEXT NOT NULL UNIQUE,
    scope          TEXT NOT NULL CHECK (scope IN ('public','private')),
    password_hash  TEXT,
    permissions    TEXT NOT NULL DEFAULT 'view' CHECK (permissions IN ('view','download','edit')),
    expires_at     TEXT,
    revoked        INTEGER NOT NULL DEFAULT 0,
    created_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_shares_token ON shares(token);
CREATE INDEX IF NOT EXISTS idx_shares_file ON shares(file_id);
CREATE INDEX IF NOT EXISTS idx_shares_owner ON shares(owner_id);

CREATE TABLE IF NOT EXISTS teams (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    created_by  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS team_members (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id    INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role       TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('owner','member')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (team_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_team_members_user ON team_members(user_id);

CREATE TABLE IF NOT EXISTS team_folders (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id    INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    folder_id  INTEGER NOT NULL REFERENCES folders(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (team_id, folder_id)
);

CREATE TABLE IF NOT EXISTS trash (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id            INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    original_folder_id INTEGER REFERENCES folders(id) ON DELETE SET NULL,
    trashed_by         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    trashed_at         TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_trash_file ON trash(file_id);

CREATE TABLE IF NOT EXISTS storage_quota (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    used_bytes INTEGER NOT NULL DEFAULT 0,
    soft_limit INTEGER,
    hard_limit INTEGER,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_quota_user ON storage_quota(user_id);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS blocked_file_types (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    extension   TEXT NOT NULL UNIQUE,
    reason      TEXT NOT NULL DEFAULT 'blocked by admin policy',
    created_by  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    actor_name  TEXT NOT NULL,
    action      TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id   TEXT,
    ip_address  TEXT,
    metadata    TEXT NOT NULL DEFAULT '{}',
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_events(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_events(action);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_events(created_at);

CREATE TABLE IF NOT EXISTS realtime_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type  TEXT NOT NULL,
    payload     TEXT NOT NULL DEFAULT '{}',
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_realtime_id ON realtime_events(id);

CREATE TABLE IF NOT EXISTS audit_exports (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    format      TEXT NOT NULL CHECK (format IN ('csv','json')),
    row_count   INTEGER NOT NULL DEFAULT 0,
    filename    TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
