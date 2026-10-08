// Package database owns the SQLite connection, schema and seed fixtures.
package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	_ "modernc.org/sqlite"
)

// Open opens (creating if needed) the SQLite database and applies the schema.
func Open(path string) (*sql.DB, error) {
	if dir := filepath.Dir(path); dir != "." {
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return nil, fmt.Errorf("create db dir: %w", err)
		}
	}
	dsn := fmt.Sprintf("file:%s?_pragma=busy_timeout(5000)&_pragma=journal_mode(WAL)&_pragma=foreign_keys(1)", path)
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return nil, fmt.Errorf("open sqlite: %w", err)
	}
	db.SetMaxOpenConns(1)
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("ping sqlite: %w", err)
	}
	if err := ApplySchema(db); err != nil {
		return nil, err
	}
	return db, nil
}

// ApplySchema creates all tables if they do not exist.
func ApplySchema(db *sql.DB) error {
	if _, err := db.Exec(schemaSQL); err != nil {
		return fmt.Errorf("apply schema: %w", err)
	}
	return nil
}

// Reset drops all application tables so the database can be re-seeded.
func Reset(db *sql.DB) error {
	_, err := db.Exec(`
		PRAGMA foreign_keys = OFF;
		DROP TABLE IF EXISTS webhook_deliveries;
		DROP TABLE IF EXISTS webhooks;
		DROP TABLE IF EXISTS import_export;
		DROP TABLE IF EXISTS attachments;
		DROP TABLE IF EXISTS stored_files;
		DROP TABLE IF EXISTS issue_labels;
		DROP TABLE IF EXISTS labels;
		DROP TABLE IF EXISTS milestones;
		DROP TABLE IF EXISTS comments;
		DROP TABLE IF EXISTS assignment_and_workflow;
		DROP TABLE IF EXISTS issue_creation;
		DROP TABLE IF EXISTS issue_search;
		DROP TABLE IF EXISTS issues;
		DROP TABLE IF EXISTS private_projects;
		DROP TABLE IF EXISTS project_management;
		DROP TABLE IF EXISTS projects;
		DROP TABLE IF EXISTS project_members;
		DROP TABLE IF EXISTS audit_events;
		DROP TABLE IF EXISTS admin_operations;
		DROP TABLE IF EXISTS account_access;
		DROP TABLE IF EXISTS frontend_api_integration_and_errors;
		DROP TABLE IF EXISTS global_settings;
		DROP TABLE IF EXISTS sessions;
		DROP TABLE IF EXISTS users;
		PRAGMA foreign_keys = ON;
	`)
	return err
}

const schemaSQL = `
CREATE TABLE IF NOT EXISTS users (
	id            INTEGER PRIMARY KEY AUTOINCREMENT,
	username      TEXT NOT NULL UNIQUE,
	email         TEXT NOT NULL UNIQUE,
	display_name  TEXT NOT NULL,
	password_hash TEXT NOT NULL,
	role          TEXT NOT NULL DEFAULT 'developer',
	active        INTEGER NOT NULL DEFAULT 1,
	created_at    TEXT NOT NULL,
	updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	token      TEXT NOT NULL UNIQUE,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	created_at TEXT NOT NULL,
	expires_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);

CREATE TABLE IF NOT EXISTS projects (
	id          INTEGER PRIMARY KEY AUTOINCREMENT,
	name        TEXT NOT NULL,
	slug        TEXT NOT NULL UNIQUE,
	description TEXT NOT NULL DEFAULT '',
	visibility  TEXT NOT NULL DEFAULT 'public',
	owner_id    INTEGER NOT NULL REFERENCES users(id),
	created_at  TEXT NOT NULL,
	updated_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS labels (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
	name       TEXT NOT NULL,
	color      TEXT NOT NULL DEFAULT '1f883d',
	UNIQUE (project_id, name)
);

CREATE TABLE IF NOT EXISTS milestones (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
	title      TEXT NOT NULL,
	is_open    INTEGER NOT NULL DEFAULT 1,
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issues (
	id           INTEGER PRIMARY KEY AUTOINCREMENT,
	project_id   INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
	number       INTEGER NOT NULL,
	title        TEXT NOT NULL,
	body         TEXT NOT NULL DEFAULT '',
	priority     TEXT NOT NULL DEFAULT 'medium',
	status       TEXT NOT NULL DEFAULT 'open',
	milestone_id INTEGER REFERENCES milestones(id) ON DELETE SET NULL,
	created_by   INTEGER NOT NULL REFERENCES users(id),
	assignee_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
	created_at   TEXT NOT NULL,
	updated_at   TEXT NOT NULL,
	UNIQUE (project_id, number)
);

CREATE TABLE IF NOT EXISTS issue_labels (
	issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
	label_id INTEGER NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
	PRIMARY KEY (issue_id, label_id)
);

CREATE TABLE IF NOT EXISTS comments (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	issue_id   INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
	author_id  INTEGER NOT NULL REFERENCES users(id),
	body       TEXT NOT NULL,
	created_at TEXT NOT NULL,
	updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_comments_issue ON comments(issue_id);

CREATE TABLE IF NOT EXISTS stored_files (
	id           INTEGER PRIMARY KEY AUTOINCREMENT,
	owner_id     INTEGER NOT NULL REFERENCES users(id),
	domain_type  TEXT NOT NULL,
	domain_id    INTEGER NOT NULL,
	filename     TEXT NOT NULL,
	content_type TEXT NOT NULL,
	size         INTEGER NOT NULL,
	storage_path TEXT NOT NULL,
	sha256       TEXT NOT NULL,
	is_private   INTEGER NOT NULL DEFAULT 0,
	created_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS attachments (
	id             INTEGER PRIMARY KEY AUTOINCREMENT,
	issue_id       INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
	uploader_id    INTEGER NOT NULL REFERENCES users(id),
	stored_file_id INTEGER NOT NULL REFERENCES stored_files(id) ON DELETE CASCADE,
	created_at     TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS webhooks (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
	created_by INTEGER NOT NULL REFERENCES users(id),
	url        TEXT NOT NULL,
	secret     TEXT NOT NULL,
	active     INTEGER NOT NULL DEFAULT 1,
	events     TEXT NOT NULL,
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
	id          INTEGER PRIMARY KEY AUTOINCREMENT,
	webhook_id  INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
	event       TEXT NOT NULL,
	payload     TEXT NOT NULL,
	status      TEXT NOT NULL,
	attempts    INTEGER NOT NULL DEFAULT 0,
	response    TEXT NOT NULL DEFAULT '',
	created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_events (
	id          INTEGER PRIMARY KEY AUTOINCREMENT,
	actor_id    INTEGER NOT NULL REFERENCES users(id),
	action      TEXT NOT NULL,
	entity_type TEXT NOT NULL DEFAULT '',
	entity_id   INTEGER NOT NULL DEFAULT 0,
	detail      TEXT NOT NULL DEFAULT '',
	created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_events(actor_id);

-- Use-case workflow record tables (contract entities). Each row documents one
-- executed workflow operation; authoritative domain data lives in the domain
-- tables above.
CREATE TABLE IF NOT EXISTS account_access (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS project_management (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issue_creation (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issue_search (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS assignment_and_workflow (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS private_projects (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS import_export (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_operations (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS frontend_api_integration_and_errors (
	id         INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	action     TEXT NOT NULL,
	subject_id INTEGER NOT NULL DEFAULT 0,
	summary    TEXT NOT NULL DEFAULT '',
	detail     TEXT NOT NULL DEFAULT '',
	status     TEXT NOT NULL DEFAULT 'ok',
	created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS global_settings (
	key   TEXT PRIMARY KEY,
	value TEXT NOT NULL
);

-- Project memberships for private projects (role: member | maintainer).
CREATE TABLE IF NOT EXISTS project_members (
	project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
	user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	role       TEXT NOT NULL DEFAULT 'member',
	PRIMARY KEY (project_id, user_id)
);
`
