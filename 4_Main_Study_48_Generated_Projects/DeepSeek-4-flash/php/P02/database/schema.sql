PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('author','reviewer','chair','admin')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    user_agent TEXT,
    ip_address TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS conference_phases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE CHECK (name IN ('submission','review','rebuttal','decision')),
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS paper_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    abstract TEXT NOT NULL,
    keywords TEXT NOT NULL DEFAULT '',
    manuscript_path TEXT,
    status TEXT NOT NULL DEFAULT 'submitted'
        CHECK (status IN ('submitted','under_review','in_rebuttal','accepted','rejected','decided')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS stored_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    mime TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    kind TEXT NOT NULL DEFAULT 'manuscript' CHECK (kind IN ('manuscript','supplementary','export')),
    submission_id INTEGER REFERENCES paper_submissions(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reviewer_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    reviewer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','accepted','declined','completed')),
    conflict_of_interest INTEGER NOT NULL DEFAULT 0,
    conflict_note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (submission_id, reviewer_id)
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    reviewer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assignment_id INTEGER REFERENCES reviewer_assignments(id) ON DELETE SET NULL,
    score INTEGER CHECK (score BETWEEN 1 AND 10),
    confidence INTEGER CHECK (confidence BETWEEN 1 AND 5),
    comments TEXT NOT NULL DEFAULT '',
    private_notes TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','submitted')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (submission_id, reviewer_id)
);

CREATE TABLE IF NOT EXISTS rebuttals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    text TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'submitted' CHECK (status IN ('submitted','pending','accepted','rejected')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS decisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    decision TEXT NOT NULL CHECK (decision IN ('accept','reject','major_revision','minor_revision')),
    notification_text TEXT NOT NULL DEFAULT '',
    decided_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    decided_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    entity TEXT NOT NULL,
    entity_id INTEGER,
    detail TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS double_blind_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    viewer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    viewer_role TEXT NOT NULL,
    blind INTEGER NOT NULL DEFAULT 1,
    viewed_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS bulk_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    export_type TEXT NOT NULL CHECK (export_type IN ('submissions','reviews','decisions')),
    format TEXT NOT NULL CHECK (format IN ('csv','pdf')),
    file_path TEXT,
    requested_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'completed' CHECK (status IN ('pending','completed','failed')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    completed_at TEXT
);

CREATE TABLE IF NOT EXISTS account_access_and_recovery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('reset_request','login','signout','recovery')),
    token TEXT,
    token_expires_at TEXT,
    payload TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','used','expired','completed')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS submission_discovery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    search_query TEXT NOT NULL DEFAULT '',
    status_filter TEXT NOT NULL DEFAULT '',
    result_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS manuscript_access (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    submission_id INTEGER NOT NULL REFERENCES paper_submissions(id) ON DELETE CASCADE,
    file_id INTEGER NOT NULL REFERENCES stored_files(id) ON DELETE CASCADE,
    accessed_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    access_type TEXT NOT NULL DEFAULT 'view' CHECK (access_type IN ('view','download')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS frontend_api_integration_and_errors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    endpoint TEXT NOT NULL,
    error_code TEXT NOT NULL,
    error_message TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'reported' CHECK (status IN ('reported','acknowledged','resolved')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS realtime_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
