-- Workflow Automation / Agentic Task Platform schema (P14)

CREATE TABLE IF NOT EXISTS users (
    id            TEXT PRIMARY KEY,
    email         TEXT NOT NULL UNIQUE,
    display_name  TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('user', 'admin')),
    status        TEXT NOT NULL CHECK (status IN ('active', 'disabled')) DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sessions (
    id         TEXT PRIMARY KEY,
    user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

CREATE TABLE IF NOT EXISTS account_access (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action      TEXT NOT NULL CHECK (action IN ('register', 'sign_in', 'sign_out', 'reset')),
    status      TEXT NOT NULL CHECK (status IN ('ok', 'error')) DEFAULT 'ok',
    detail      TEXT NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_account_access_user ON account_access(user_id);

CREATE TABLE IF NOT EXISTS workflow_creation (
    id            TEXT PRIMARY KEY,
    user_id       TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name          TEXT NOT NULL,
    description   TEXT NOT NULL DEFAULT '',
    trigger_kind  TEXT NOT NULL CHECK (trigger_kind IN ('manual', 'webhook', 'schedule')),
    cron_expr     TEXT NOT NULL DEFAULT '',
    steps_json    TEXT NOT NULL DEFAULT '[]',
    status        TEXT NOT NULL CHECK (status IN ('draft', 'active', 'archived')) DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);
CREATE INDEX IF NOT EXISTS idx_workflow_creation_user ON workflow_creation(user_id);

CREATE TABLE IF NOT EXISTS tool_catalog (
    id            TEXT PRIMARY KEY,
    name          TEXT NOT NULL UNIQUE,
    kind          TEXT NOT NULL CHECK (kind IN ('http', 'shell', 'file_write', 'file_read', 'delay', 'log')),
    description   TEXT NOT NULL DEFAULT '',
    default_json  TEXT NOT NULL DEFAULT '{}',
    enabled       INTEGER NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS workflow_tools (
    workflow_id TEXT NOT NULL REFERENCES workflow_creation(id) ON DELETE CASCADE,
    tool_id     TEXT NOT NULL REFERENCES tool_catalog(id) ON DELETE CASCADE,
    PRIMARY KEY (workflow_id, tool_id)
);

CREATE TABLE IF NOT EXISTS task_execution (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id TEXT NOT NULL REFERENCES workflow_creation(id) ON DELETE CASCADE,
    trigger     TEXT NOT NULL CHECK (trigger IN ('manual', 'webhook', 'schedule')) DEFAULT 'manual',
    status      TEXT NOT NULL CHECK (status IN ('queued', 'running', 'success', 'failed', 'canceled')) DEFAULT 'queued',
    started_at  DATETIME,
    finished_at DATETIME,
    detail      TEXT NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_task_execution_user ON task_execution(user_id);
CREATE INDEX IF NOT EXISTS idx_task_execution_workflow ON task_execution(workflow_id);

CREATE TABLE IF NOT EXISTS task_steps (
    id           TEXT PRIMARY KEY,
    run_id       TEXT NOT NULL REFERENCES task_execution(id) ON DELETE CASCADE,
    seq          INTEGER NOT NULL,
    name         TEXT NOT NULL,
    tool_id      TEXT REFERENCES tool_catalog(id) ON DELETE SET NULL,
    status       TEXT NOT NULL CHECK (status IN ('pending', 'running', 'success', 'failed', 'skipped')) DEFAULT 'pending',
    started_at   DATETIME,
    finished_at  DATETIME,
    output       TEXT NOT NULL DEFAULT '',
    error        TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_task_steps_run ON task_steps(run_id);

CREATE TABLE IF NOT EXISTS scheduled_runs (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id TEXT NOT NULL REFERENCES workflow_creation(id) ON DELETE CASCADE,
    cron_expr   TEXT NOT NULL,
    next_run_at DATETIME NOT NULL,
    enabled     INTEGER NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_scheduled_runs_user ON scheduled_runs(user_id);

CREATE TABLE IF NOT EXISTS workspace_files (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id TEXT REFERENCES workflow_creation(id) ON DELETE SET NULL,
    path        TEXT NOT NULL,
    size        INTEGER NOT NULL DEFAULT 0,
    mime_type   TEXT NOT NULL DEFAULT 'text/plain',
    content     TEXT NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, path)
);
CREATE INDEX IF NOT EXISTS idx_workspace_files_user ON workspace_files(user_id);

CREATE TABLE IF NOT EXISTS stored_files (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workspace_id TEXT REFERENCES workspace_files(id) ON DELETE SET NULL,
    filename    TEXT NOT NULL,
    stored_path TEXT NOT NULL,
    size        INTEGER NOT NULL DEFAULT 0,
    mime_type   TEXT NOT NULL DEFAULT 'application/octet-stream',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_stored_files_user ON stored_files(user_id);

CREATE TABLE IF NOT EXISTS webhook_triggers (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id TEXT NOT NULL REFERENCES workflow_creation(id) ON DELETE CASCADE,
    token       TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    revoked     INTEGER NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_webhook_triggers_user ON webhook_triggers(user_id);

CREATE TABLE IF NOT EXISTS external_http_action (
    id           TEXT PRIMARY KEY,
    user_id      TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id  TEXT REFERENCES workflow_creation(id) ON DELETE SET NULL,
    url          TEXT NOT NULL,
    method       TEXT NOT NULL CHECK (method IN ('GET', 'POST', 'PUT', 'DELETE', 'PATCH')) DEFAULT 'GET',
    payload      TEXT NOT NULL DEFAULT '',
    status_code  INTEGER NOT NULL DEFAULT 0,
    response     TEXT NOT NULL DEFAULT '',
    error        TEXT NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_external_http_action_user ON external_http_action(user_id);

CREATE TABLE IF NOT EXISTS secrets_manager (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    masked      TEXT NOT NULL,
    cipher      TEXT NOT NULL,
    revoked     INTEGER NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);
CREATE INDEX IF NOT EXISTS idx_secrets_manager_user ON secrets_manager(user_id);

CREATE TABLE IF NOT EXISTS run_logs_and_replay (
    id          TEXT PRIMARY KEY,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    run_id      TEXT NOT NULL REFERENCES task_execution(id) ON DELETE CASCADE,
    level       TEXT NOT NULL CHECK (level IN ('info', 'warn', 'error')) DEFAULT 'info',
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_run_logs_and_replay_run ON run_logs_and_replay(run_id);
CREATE INDEX IF NOT EXISTS idx_run_logs_and_replay_user ON run_logs_and_replay(user_id);

CREATE TABLE IF NOT EXISTS sharing_and_templates (
    id           TEXT PRIMARY KEY,
    user_id      TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workflow_id  TEXT REFERENCES workflow_creation(id) ON DELETE SET NULL,
    name         TEXT NOT NULL,
    description  TEXT NOT NULL DEFAULT '',
    body_json    TEXT NOT NULL DEFAULT '{}',
    visibility   TEXT NOT NULL CHECK (visibility IN ('private', 'shared', 'public')) DEFAULT 'private',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);
CREATE INDEX IF NOT EXISTS idx_sharing_and_templates_user ON sharing_and_templates(user_id);

CREATE TABLE IF NOT EXISTS admin_governance (
    id          TEXT PRIMARY KEY,
    key         TEXT NOT NULL UNIQUE,
    value       TEXT NOT NULL,
    updated_by  TEXT REFERENCES users(id) ON DELETE SET NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_events (
    id          TEXT PRIMARY KEY,
    actor_id    TEXT REFERENCES users(id) ON DELETE SET NULL,
    action      TEXT NOT NULL,
    target      TEXT NOT NULL DEFAULT '',
    detail      TEXT NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_audit_events_actor ON audit_events(actor_id);