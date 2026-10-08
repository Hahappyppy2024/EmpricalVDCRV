PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('user','admin')) DEFAULT 'user',
  status TEXT NOT NULL CHECK (status IN ('active','disabled')) DEFAULT 'active',
  quota_bytes INTEGER NOT NULL DEFAULT 10485760,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE password_reset_tokens (
  token_hash TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  used_at TEXT
);
CREATE TABLE folders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  parent_id INTEGER REFERENCES folders(id),
  owner_id INTEGER NOT NULL REFERENCES users(id),
  team_space_id INTEGER,
  version INTEGER NOT NULL DEFAULT 1,
  trashed_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(parent_id,name)
);
CREATE TABLE team_spaces (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  root_folder_id INTEGER NOT NULL REFERENCES folders(id),
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE team_members (
  space_id INTEGER NOT NULL REFERENCES team_spaces(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role TEXT NOT NULL CHECK (role IN ('member','team_admin')),
  PRIMARY KEY(space_id,user_id)
);
CREATE TABLE stored_blobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stored_name TEXT NOT NULL UNIQUE,
  size_bytes INTEGER NOT NULL,
  checksum TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  folder_id INTEGER NOT NULL REFERENCES folders(id),
  owner_id INTEGER NOT NULL REFERENCES users(id),
  name TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  current_version_id INTEGER,
  version INTEGER NOT NULL DEFAULT 1,
  trashed_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(folder_id,name)
);
CREATE TABLE file_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
  blob_id INTEGER NOT NULL REFERENCES stored_blobs(id),
  version_number INTEGER NOT NULL,
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(file_id,version_number)
);
CREATE TABLE shares (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT NOT NULL UNIQUE,
  file_id INTEGER REFERENCES files(id) ON DELETE CASCADE,
  folder_id INTEGER REFERENCES folders(id) ON DELETE CASCADE,
  permission TEXT NOT NULL CHECK (permission IN ('view','download')),
  expires_at TEXT NOT NULL,
  revoked_at TEXT,
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK ((file_id IS NOT NULL AND folder_id IS NULL) OR (file_id IS NULL AND folder_id IS NOT NULL))
);
CREATE TABLE trash_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_type TEXT NOT NULL CHECK (item_type IN ('file','folder')),
  item_id INTEGER NOT NULL,
  owner_id INTEGER NOT NULL REFERENCES users(id),
  original_parent_id INTEGER,
  deleted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(item_type,item_id)
);
CREATE TABLE audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  team_space_id INTEGER REFERENCES team_spaces(id) ON DELETE CASCADE,
  actor_id INTEGER REFERENCES users(id),
  action TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  details TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE retention_settings (
  id INTEGER PRIMARY KEY CHECK (id=1),
  trash_days INTEGER NOT NULL,
  version INTEGER NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_folders_owner ON folders(owner_id,parent_id);
CREATE INDEX idx_files_owner ON files(owner_id,folder_id);
CREATE INDEX idx_versions_file ON file_versions(file_id,version_number);
CREATE INDEX idx_trash_owner ON trash_items(owner_id,deleted_at);
CREATE INDEX idx_audit_space ON audit_events(team_space_id,created_at);
