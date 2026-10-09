-- CMS schema: comprehensive tables for 12 use cases
PRAGMA foreign_keys = ON;

-- Roles (admin, editor, author, moderator, visitor)
CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  description TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);

-- Users with role FK
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  role_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role_id);

-- Sessions stored in SQLite (HTTP-only cookie references this row)
CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

-- Account access audit log (CMS-01)
CREATE TABLE IF NOT EXISTS account_access (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  event TEXT NOT NULL,
  ip_address TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Articles / content authoring (CMS-02)
CREATE TABLE IF NOT EXISTS content_authoring (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  slug TEXT UNIQUE,
  summary TEXT,
  body TEXT,
  tags TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  author_id INTEGER NOT NULL,
  category_id INTEGER,
  template_id INTEGER,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (author_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (template_id) REFERENCES page_templates(id)
);
CREATE INDEX IF NOT EXISTS idx_articles_author ON content_authoring(author_id);
CREATE INDEX IF NOT EXISTS idx_articles_status ON content_authoring(status);

-- Rich text editor revisions (CMS-03)
CREATE TABLE IF NOT EXISTS rich_text_editor (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL,
  body_html TEXT,
  css_classes TEXT,
  preview_token TEXT,
  updated_by INTEGER NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (article_id) REFERENCES content_authoring(id) ON DELETE CASCADE,
  FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Media library / stored files (CMS-04, CMS-11)
CREATE TABLE IF NOT EXISTS media_library (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_name TEXT NOT NULL,
  original_name TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  url_path TEXT NOT NULL,
  owner_id INTEGER NOT NULL,
  alt_text TEXT,
  is_private INTEGER NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_media_owner ON media_library(owner_id);

-- Publishing workflow state machine (CMS-05)
CREATE TABLE IF NOT EXISTS publishing_workflow (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL,
  state TEXT NOT NULL DEFAULT 'draft',
  scheduled_at TEXT,
  published_at TEXT,
  reviewer_id INTEGER,
  notes TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (article_id) REFERENCES content_authoring(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

-- Public site routes / published entries cache (CMS-06)
CREATE TABLE IF NOT EXISTS public_site (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  route_path TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL,
  excerpt TEXT,
  body TEXT,
  article_id INTEGER,
  template_id INTEGER,
  published_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (article_id) REFERENCES content_authoring(id) ON DELETE SET NULL,
  FOREIGN KEY (template_id) REFERENCES page_templates(id)
);
CREATE INDEX IF NOT EXISTS idx_public_path ON public_site(route_path);

-- Comments (CMS-07)
CREATE TABLE IF NOT EXISTS comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL,
  author_name TEXT NOT NULL,
  author_email TEXT,
  author_id INTEGER,
  body TEXT NOT NULL,
  state TEXT NOT NULL DEFAULT 'pending',
  moderator_id INTEGER,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (article_id) REFERENCES content_authoring(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id),
  FOREIGN KEY (moderator_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_comments_state ON comments(state);

-- Page templates (CMS-08)
CREATE TABLE IF NOT EXISTS page_templates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  description TEXT,
  layout_html TEXT NOT NULL,
  regions TEXT NOT NULL DEFAULT '[]',
  is_default INTEGER NOT NULL DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now'))
);

-- Navigation menus (CMS-08)
CREATE TABLE IF NOT EXISTS navigation_menus (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  label TEXT NOT NULL,
  url TEXT NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  parent_id INTEGER,
  FOREIGN KEY (parent_id) REFERENCES navigation_menus(id) ON DELETE CASCADE
);

-- User and role management audit (CMS-09)
CREATE TABLE IF NOT EXISTS user_and_role_management (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  new_role_id INTEGER,
  action TEXT NOT NULL,
  performed_by INTEGER NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (new_role_id) REFERENCES roles(id),
  FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- Audit events (CMS-09)
CREATE TABLE IF NOT EXISTS audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id INTEGER,
  action TEXT NOT NULL,
  resource TEXT NOT NULL,
  details TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (actor_id) REFERENCES users(id)
);

-- Plugin/settings (CMS-10)
CREATE TABLE IF NOT EXISTS plugin_settings_panel (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  key TEXT UNIQUE NOT NULL,
  value TEXT,
  category TEXT NOT NULL DEFAULT 'general',
  updated_by INTEGER,
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Import/export jobs (CMS-11)
CREATE TABLE IF NOT EXISTS import_export (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_type TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'queued',
  payload_json TEXT,
  file_path TEXT,
  requested_by INTEGER NOT NULL,
  result_count INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  completed_at TEXT,
  FOREIGN KEY (requested_by) REFERENCES users(id)
);

-- Frontend API integration log (CMS-12)
CREATE TABLE IF NOT EXISTS frontend_api_integration_and_errors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  endpoint TEXT NOT NULL,
  method TEXT NOT NULL,
  response_code INTEGER NOT NULL,
  error_class TEXT,
  message TEXT,
  observed_by INTEGER,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (observed_by) REFERENCES users(id)
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  slug TEXT UNIQUE NOT NULL
);
