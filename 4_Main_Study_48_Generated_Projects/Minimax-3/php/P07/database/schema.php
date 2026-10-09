<?php
$stmt = <<<'SQL'
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin','user','recipient')),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    csrf_token TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL,
    revoked_at TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS csrf_tokens (
    selector TEXT PRIMARY KEY,
    token_hash TEXT NOT NULL,
    user_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS account_recoveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    selector TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    owner_id INTEGER,
    team_id INTEGER,
    parent_id INTEGER,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','trashed')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    deleted_at TEXT,
    FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(team_id) REFERENCES team_spaces(id) ON DELETE SET NULL,
    FOREIGN KEY(parent_id) REFERENCES folders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS team_spaces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    created_by INTEGER NOT NULL,
    root_folder_id INTEGER,
    quota_limit INTEGER NOT NULL DEFAULT 1073741824,
    used_bytes INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS team_memberships (
    team_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('owner','editor','viewer')),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','invited','suspended')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY(team_id,user_id),
    FOREIGN KEY(team_id) REFERENCES team_spaces(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stored_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    folder_id INTEGER,
    owner_id INTEGER,
    team_id INTEGER,
    original_name TEXT NOT NULL,
    storage_name TEXT NOT NULL UNIQUE,
    description TEXT,
    mime_type TEXT,
    size INTEGER NOT NULL DEFAULT 0,
    checksum TEXT,
    purpose TEXT NOT NULL DEFAULT 'user_file' CHECK(purpose IN ('user_file','audit_export')),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','trashed')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    deleted_at TEXT,
    FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE SET NULL,
    FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(team_id) REFERENCES team_spaces(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS file_tags (
    file_id INTEGER NOT NULL,
    tag TEXT NOT NULL,
    PRIMARY KEY(file_id,tag),
    FOREIGN KEY(file_id) REFERENCES stored_files(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    version_number INTEGER NOT NULL,
    stored_file_id INTEGER NOT NULL,
    uploaded_by INTEGER NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    change_summary TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(file_id,version_number),
    FOREIGN KEY(file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    FOREIGN KEY(stored_file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS file_uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    uploaded_by INTEGER NOT NULL,
    folder_id INTEGER,
    original_name TEXT NOT NULL,
    description TEXT,
    tags TEXT,
    size INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'completed' CHECK(status IN ('completed','rejected')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER,
    folder_id INTEGER,
    created_by INTEGER NOT NULL,
    token_selector TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    scope TEXT NOT NULL CHECK(scope IN ('public','private')),
    permission TEXT NOT NULL DEFAULT 'preview' CHECK(permission IN ('preview','download')),
    expires_at TEXT,
    revoked_at TEXT,
    shared_with_user_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trash_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_type TEXT NOT NULL CHECK(item_type IN ('file','folder')),
    item_id INTEGER NOT NULL,
    owner_id INTEGER,
    team_id INTEGER,
    original_name TEXT NOT NULL,
    original_parent_id INTEGER,
    deleted_by INTEGER NOT NULL,
    reason TEXT,
    purged_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(item_type,item_id),
    FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(team_id) REFERENCES team_spaces(id) ON DELETE SET NULL,
    FOREIGN KEY(deleted_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS storage_quotas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    limit_bytes INTEGER NOT NULL,
    used_bytes INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS searches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    query TEXT,
    owner TEXT,
    tag TEXT,
    from_date TEXT,
    to_date TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    details TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    format TEXT NOT NULL DEFAULT 'csv' CHECK(format IN ('csv','json')),
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','ready','expired')),
    file_id INTEGER,
    filters TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(file_id) REFERENCES stored_files(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS admin_settings (
    id INTEGER PRIMARY KEY CHECK(id=1),
    default_user_quota INTEGER NOT NULL,
    default_team_quota INTEGER NOT NULL,
    retention_days INTEGER NOT NULL,
    blocked_file_types TEXT NOT NULL DEFAULT '',
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS account_access (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    status TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS folder_management (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    folder_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sharing_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    share_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS team_spaces_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS version_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS trash_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trash_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS storage_quota_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quota_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_log_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    export_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admin_console_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_id INTEGER,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS file_download_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    user_id INTEGER,
    access_method TEXT NOT NULL,
    action TEXT NOT NULL,
    success INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_folders_owner ON folders(owner_id);
CREATE INDEX IF NOT EXISTS idx_folders_team ON folders(team_id);
CREATE INDEX IF NOT EXISTS idx_folders_parent ON folders(parent_id);
CREATE INDEX IF NOT EXISTS idx_stored_files_owner ON stored_files(owner_id);
CREATE INDEX IF NOT EXISTS idx_stored_files_team ON stored_files(team_id);
CREATE INDEX IF NOT EXISTS idx_stored_files_folder ON stored_files(folder_id);
CREATE INDEX IF NOT EXISTS idx_versions_file ON versions(file_id);
CREATE INDEX IF NOT EXISTS idx_shares_file ON shares(file_id);
CREATE INDEX IF NOT EXISTS idx_shares_folder ON shares(folder_id);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_events(user_id);
CREATE INDEX IF NOT EXISTS idx_trash_owner ON trash_items(owner_id);
SQL;
return $stmt;