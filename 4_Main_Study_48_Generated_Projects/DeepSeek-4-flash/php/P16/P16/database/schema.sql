-- P16 - Server Monitoring & Job Control Panel - SQLite schema
-- Executed by bin/reset-db.php and database/seed.php

PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS audit_events;
DROP TABLE IF EXISTS operator_actions;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS health_targets;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS config_keys;
DROP TABLE IF EXISTS backups;
DROP TABLE IF EXISTS stored_files;
DROP TABLE IF EXISTS job_runs;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS mock_services;
DROP TABLE IF EXISTS log_entries;
DROP TABLE IF EXISTS server_dashboard;
DROP TABLE IF EXISTS account_access;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- SYS-01: User; Session; AccountAccess
CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    full_name     TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('admin', 'operator', 'viewer')),
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL
);

CREATE TABLE sessions (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      TEXT    NOT NULL UNIQUE,
    ip_address TEXT    NOT NULL DEFAULT '',
    user_agent TEXT    NOT NULL DEFAULT '',
    expires_at TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

CREATE TABLE account_access (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    username   TEXT    NOT NULL DEFAULT '',
    action     TEXT    NOT NULL DEFAULT 'access_record'
               CHECK (action IN ('register', 'login', 'logout', 'password_reset', 'session_revoke', 'access_record')),
    outcome    TEXT    NOT NULL DEFAULT 'success' CHECK (outcome IN ('success', 'failure')),
    ip_address TEXT    NOT NULL DEFAULT '',
    details    TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL
);
CREATE INDEX idx_access_user ON account_access(user_id);

-- SYS-02: ServerDashboard (metric snapshots)
CREATE TABLE server_dashboard (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    server_name     TEXT    NOT NULL,
    hostname        TEXT    NOT NULL DEFAULT '',
    cpu_pct         REAL    NOT NULL DEFAULT 0,
    memory_pct      REAL    NOT NULL DEFAULT 0,
    disk_pct        REAL    NOT NULL DEFAULT 0,
    uptime_seconds  INTEGER NOT NULL DEFAULT 0,
    service_status  TEXT    NOT NULL DEFAULT 'unknown'
                    CHECK (service_status IN ('ok', 'degraded', 'down', 'maintenance', 'unknown')),
    recorded_by     INTEGER REFERENCES users(id),
    created_at      TEXT    NOT NULL
);
CREATE INDEX idx_dashboard_server ON server_dashboard(server_name, id);

-- SYS-03: LogViewer (log entries mirrored to storage/logs/*.log)
CREATE TABLE log_entries (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name  TEXT    NOT NULL,
    level      TEXT    NOT NULL DEFAULT 'info'
               CHECK (level IN ('debug', 'info', 'notice', 'warning', 'error', 'critical')),
    source     TEXT    NOT NULL DEFAULT 'system',
    message    TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);
CREATE INDEX idx_log_file ON log_entries(file_name, id);

-- SYS-04: ServiceControl (mock services)
CREATE TABLE mock_services (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    name           TEXT    NOT NULL UNIQUE,
    description    TEXT    NOT NULL DEFAULT '',
    status         TEXT    NOT NULL DEFAULT 'stopped'
                   CHECK (status IN ('stopped', 'starting', 'running', 'stopping', 'restarting', 'down', 'unknown')),
    uptime_seconds INTEGER NOT NULL DEFAULT 0,
    controlled_by  INTEGER REFERENCES users(id),
    last_action    TEXT    NOT NULL DEFAULT '',
    last_action_at TEXT,
    updated_at     TEXT    NOT NULL
);

-- SYS-05: JobScheduler
CREATE TABLE jobs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    profile     TEXT    NOT NULL,
    description TEXT    NOT NULL DEFAULT '',
    schedule    TEXT    NOT NULL DEFAULT 'manual'
                CHECK (schedule IN ('manual', 'hourly', 'daily', 'weekly', 'monthly')),
    status      TEXT    NOT NULL DEFAULT 'active'
                CHECK (status IN ('active', 'paused', 'deleted')),
    owner_id    INTEGER NOT NULL REFERENCES users(id),
    created_at  TEXT    NOT NULL,
    updated_at  TEXT    NOT NULL
);
CREATE INDEX idx_jobs_owner ON jobs(owner_id, status);

-- SYS-06: JobExecutionHistory
CREATE TABLE job_runs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id       INTEGER NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    run_number   INTEGER NOT NULL DEFAULT 1,
    output       TEXT    NOT NULL DEFAULT '',
    exit_status  INTEGER NOT NULL DEFAULT 0,
    duration_ms  INTEGER NOT NULL DEFAULT 0,
    retry_count  INTEGER NOT NULL DEFAULT 0,
    started_at   TEXT    NOT NULL,
    finished_at  TEXT    NOT NULL
);
CREATE INDEX idx_runs_job ON job_runs(job_id, id);

-- SYS-07: BackupManager + StoredFile
CREATE TABLE stored_files (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id      INTEGER NOT NULL REFERENCES users(id),
    filename      TEXT    NOT NULL,
    original_name TEXT    NOT NULL DEFAULT '',
    disk_path     TEXT    NOT NULL,
    size_bytes    INTEGER NOT NULL DEFAULT 0,
    mime_type     TEXT    NOT NULL DEFAULT 'application/octet-stream',
    sha256        TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL
);
CREATE INDEX idx_files_owner ON stored_files(owner_id);

CREATE TABLE backups (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    file_id     INTEGER REFERENCES stored_files(id) ON DELETE SET NULL,
    owner_id    INTEGER NOT NULL REFERENCES users(id),
    backup_type TEXT    NOT NULL DEFAULT 'manual' CHECK (backup_type IN ('manual', 'scheduled')),
    status      TEXT    NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'restored', 'deleted')),
    size_bytes  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL,
    restored_at TEXT
);
CREATE INDEX idx_backups_owner ON backups(owner_id, status);

-- SYS-08: ConfigurationEditor
CREATE TABLE config_keys (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key    TEXT    NOT NULL UNIQUE,
    config_value  TEXT    NOT NULL DEFAULT '',
    description   TEXT    NOT NULL DEFAULT '',
    status        TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'pending')),
    pending_value TEXT,
    updated_by    INTEGER REFERENCES users(id),
    updated_at    TEXT    NOT NULL
);

-- SYS-09: AlertCenter
CREATE TABLE alerts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    severity    TEXT    NOT NULL DEFAULT 'info' CHECK (severity IN ('critical', 'warning', 'info')),
    status      TEXT    NOT NULL DEFAULT 'open'
                CHECK (status IN ('open', 'acknowledged', 'assigned', 'closed')),
    source      TEXT    NOT NULL DEFAULT 'system',
    assignee_id INTEGER REFERENCES users(id),
    created_by  INTEGER REFERENCES users(id),
    comments    TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL,
    updated_at  TEXT    NOT NULL
);
CREATE INDEX idx_alerts_status ON alerts(status, id);

-- SYS-10: HealthCheckTargets
CREATE TABLE health_targets (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT    NOT NULL UNIQUE,
    protocol         TEXT    NOT NULL DEFAULT 'http' CHECK (protocol IN ('http', 'tcp')),
    target           TEXT    NOT NULL,
    port             INTEGER,
    interval_seconds INTEGER NOT NULL DEFAULT 60,
    status           TEXT    NOT NULL DEFAULT 'unknown' CHECK (status IN ('up', 'down', 'unknown')),
    last_code        INTEGER,
    last_latency_ms  INTEGER,
    last_checked_at  TEXT,
    created_by       INTEGER REFERENCES users(id),
    created_at       TEXT    NOT NULL
);

-- SYS-11: ApiTokenManager
CREATE TABLE api_tokens (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT    NOT NULL,
    token_hash   TEXT    NOT NULL UNIQUE,
    token_prefix TEXT    NOT NULL DEFAULT '',
    owner_id     INTEGER NOT NULL REFERENCES users(id),
    created_by   INTEGER NOT NULL REFERENCES users(id),
    revoked_at   TEXT,
    last_used_at TEXT,
    created_at   TEXT    NOT NULL
);
CREATE INDEX idx_tokens_owner ON api_tokens(owner_id);

-- SYS-12: AuditLogsAndAdminOperations
CREATE TABLE audit_events (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER REFERENCES users(id),
    username   TEXT    NOT NULL DEFAULT '',
    action     TEXT    NOT NULL,
    module     TEXT    NOT NULL DEFAULT '',
    detail     TEXT    NOT NULL DEFAULT '',
    ip_address TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL
);
CREATE INDEX idx_audit_created ON audit_events(created_at DESC);
CREATE INDEX idx_audit_module ON audit_events(module, id);

CREATE TABLE operator_actions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id    INTEGER NOT NULL REFERENCES users(id),
    operator_id INTEGER REFERENCES users(id),
    action      TEXT    NOT NULL,
    detail      TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL
);
