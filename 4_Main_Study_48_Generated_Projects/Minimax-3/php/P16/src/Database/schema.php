<?php
/**
 * SQLite schema for P16 — Server Monitoring & Job Control Panel.
 * Idempotent: safe to run on existing databases.
 */

declare(strict_types=1);

return <<<'SQL'

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('operator','admin')),
    full_name     TEXT    NOT NULL,
    enabled       INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id  TEXT    NOT NULL UNIQUE,
    user_id     INTEGER NOT NULL,
    ip_address  TEXT    NOT NULL DEFAULT '',
    user_agent  TEXT    NOT NULL DEFAULT '',
    payload     TEXT    NOT NULL DEFAULT '{}',
    expires_at  TEXT    NOT NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);

CREATE TABLE IF NOT EXISTS metric_snapshots (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    host          TEXT    NOT NULL,
    cpu_pct       REAL    NOT NULL,
    memory_pct    REAL    NOT NULL,
    disk_pct      REAL    NOT NULL,
    uptime_sec    INTEGER NOT NULL,
    load_avg      REAL    NOT NULL DEFAULT 0,
    service_state TEXT    NOT NULL DEFAULT 'healthy',
    captured_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_metric_host ON metric_snapshots(host, captured_at);

CREATE TABLE IF NOT EXISTS log_files (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL UNIQUE,
    path          TEXT    NOT NULL,
    size_bytes    INTEGER NOT NULL DEFAULT 0,
    source        TEXT    NOT NULL DEFAULT 'system',
    description   TEXT    NOT NULL DEFAULT '',
    last_seen_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS log_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    log_file_id INTEGER NOT NULL,
    ts          TEXT    NOT NULL,
    level       TEXT    NOT NULL,
    message     TEXT    NOT NULL,
    context     TEXT    NOT NULL DEFAULT '{}',
    FOREIGN KEY (log_file_id) REFERENCES log_files(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_log_entries_file ON log_entries(log_file_id, ts);

CREATE TABLE IF NOT EXISTS services (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL UNIQUE,
    description   TEXT    NOT NULL DEFAULT '',
    state         TEXT    NOT NULL DEFAULT 'stopped' CHECK (state IN ('running','stopped','restarting','failed')),
    pid           INTEGER NOT NULL DEFAULT 0,
    started_at    TEXT,
    last_action   TEXT    NOT NULL DEFAULT '',
    last_actor_id INTEGER,
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (last_actor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS job_profiles (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT    NOT NULL UNIQUE,
    name        TEXT    NOT NULL,
    description TEXT    NOT NULL DEFAULT '',
    command     TEXT    NOT NULL,
    approved    INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS scheduled_jobs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id      INTEGER NOT NULL,
    profile_id    INTEGER NOT NULL,
    name          TEXT    NOT NULL,
    cron_expr     TEXT    NOT NULL DEFAULT '* * * * *',
    state         TEXT    NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','queued','running','paused','completed','failed','deleted')),
    next_run_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    last_run_at   TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id)   REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES job_profiles(id)   ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS idx_jobs_owner ON scheduled_jobs(owner_id, state);

CREATE TABLE IF NOT EXISTS job_runs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id        INTEGER NOT NULL,
    started_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    finished_at   TEXT,
    exit_code     INTEGER NOT NULL DEFAULT -1,
    status        TEXT    NOT NULL DEFAULT 'running' CHECK (status IN ('running','success','failed','timeout')),
    output        TEXT    NOT NULL DEFAULT '',
    error_output  TEXT    NOT NULL DEFAULT '',
    duration_ms   INTEGER NOT NULL DEFAULT 0,
    retry_count   INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES scheduled_jobs(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_job_runs_job ON job_runs(job_id, started_at);

CREATE TABLE IF NOT EXISTS stored_files (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id      INTEGER NOT NULL,
    kind          TEXT    NOT NULL DEFAULT 'backup' CHECK (kind IN ('backup','log','config','export')),
    name          TEXT    NOT NULL,
    original_name TEXT    NOT NULL,
    mime_type     TEXT    NOT NULL DEFAULT 'application/octet-stream',
    size_bytes    INTEGER NOT NULL DEFAULT 0,
    checksum      TEXT    NOT NULL DEFAULT '',
    storage_path  TEXT    NOT NULL,
    description   TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_stored_owner ON stored_files(owner_id, kind);

CREATE TABLE IF NOT EXISTS configuration_keys (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    key             TEXT    NOT NULL UNIQUE,
    value           TEXT    NOT NULL DEFAULT '',
    category        TEXT    NOT NULL DEFAULT 'general',
    description     TEXT    NOT NULL DEFAULT '',
    pending_value   TEXT,
    pending_actor   INTEGER,
    pending_at      TEXT,
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_actor   INTEGER,
    FOREIGN KEY (pending_actor) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_actor) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS alerts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    severity      TEXT    NOT NULL CHECK (severity IN ('info','warning','critical')),
    title         TEXT    NOT NULL,
    message       TEXT    NOT NULL DEFAULT '',
    source        TEXT    NOT NULL DEFAULT 'system',
    state         TEXT    NOT NULL DEFAULT 'open' CHECK (state IN ('open','acknowledged','assigned','resolved','closed')),
    assignee_id   INTEGER,
    acknowledged_by INTEGER,
    acknowledged_at TEXT,
    resolved_at   TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (assignee_id)       REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by)   REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_alerts_state ON alerts(state, severity);

CREATE TABLE IF NOT EXISTS alert_comments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    alert_id    INTEGER NOT NULL,
    author_id   INTEGER NOT NULL,
    body        TEXT    NOT NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (alert_id)  REFERENCES alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id)  ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS health_check_targets (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id      INTEGER NOT NULL,
    name          TEXT    NOT NULL,
    kind          TEXT    NOT NULL CHECK (kind IN ('http','tcp','script')),
    target        TEXT    NOT NULL,
    interval_sec  INTEGER NOT NULL DEFAULT 60,
    timeout_ms    INTEGER NOT NULL DEFAULT 2000,
    state         TEXT    NOT NULL DEFAULT 'unknown' CHECK (state IN ('unknown','healthy','degraded','down')),
    last_check_at TEXT,
    last_error    TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_health_owner ON health_check_targets(owner_id, state);

CREATE TABLE IF NOT EXISTS api_tokens (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id     INTEGER NOT NULL,
    name         TEXT    NOT NULL,
    token_hash   TEXT    NOT NULL UNIQUE,
    token_prefix TEXT    NOT NULL,
    scopes       TEXT    NOT NULL DEFAULT 'read',
    state        TEXT    NOT NULL DEFAULT 'active' CHECK (state IN ('active','revoked')),
    last_used_at TEXT,
    expires_at   TEXT,
    created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    revoked_at   TEXT,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_api_tokens_owner ON api_tokens(owner_id, state);

CREATE TABLE IF NOT EXISTS audit_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id    INTEGER,
    actor_name  TEXT    NOT NULL DEFAULT 'system',
    action      TEXT    NOT NULL,
    target      TEXT    NOT NULL DEFAULT '',
    detail      TEXT    NOT NULL DEFAULT '{}',
    ip_address  TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_events(actor_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_events(action, created_at);

SQL;