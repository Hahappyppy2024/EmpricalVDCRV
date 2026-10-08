package db

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	_ "modernc.org/sqlite"
)

// Open opens (creating if needed) the SQLite database using the pure-Go
// modernc.org/sqlite driver. No CGO is required.
func Open(path string) (*sql.DB, error) {
	if dir := filepath.Dir(path); dir != "" && dir != "." {
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return nil, fmt.Errorf("create data dir: %w", err)
		}
	}
	dsn := "file:" + path +
		"?_pragma=foreign_keys(1)" +
		"&_pragma=busy_timeout(5000)" +
		"&_pragma=journal_mode(WAL)"
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return nil, fmt.Errorf("open sqlite: %w", err)
	}
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("ping sqlite: %w", err)
	}
	return db, nil
}

// Migrate creates every table required by the platform. It is idempotent.
func Migrate(db *sql.DB) error {
	stmts := []string{
		`CREATE TABLE IF NOT EXISTS users (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			username TEXT NOT NULL UNIQUE,
			email TEXT NOT NULL UNIQUE,
			password_hash TEXT NOT NULL,
			role TEXT NOT NULL DEFAULT 'user',
			active INTEGER NOT NULL DEFAULT 1,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS sessions (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			token TEXT NOT NULL UNIQUE,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			created_at TEXT NOT NULL,
			expires_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS account_accesses (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			account TEXT NOT NULL,
			action TEXT NOT NULL,
			status TEXT NOT NULL DEFAULT 'ok',
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS workflows (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			description TEXT NOT NULL DEFAULT '',
			trigger_type TEXT NOT NULL DEFAULT 'manual',
			steps_json TEXT NOT NULL DEFAULT '[]',
			conditions_json TEXT NOT NULL DEFAULT '{}',
			enabled INTEGER NOT NULL DEFAULT 1,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL,
			UNIQUE(user_id, name)
		)`,
		`CREATE TABLE IF NOT EXISTS tools (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			name TEXT NOT NULL UNIQUE,
			category TEXT NOT NULL,
			description TEXT NOT NULL DEFAULT '',
			version TEXT NOT NULL DEFAULT '1.0.0',
			is_public INTEGER NOT NULL DEFAULT 1,
			enabled INTEGER NOT NULL DEFAULT 1,
			created_by INTEGER NOT NULL,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS runs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			workflow_id INTEGER NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
			trigger TEXT NOT NULL DEFAULT 'manual',
			status TEXT NOT NULL DEFAULT 'pending',
			steps_json TEXT NOT NULL DEFAULT '[]',
			logs_json TEXT NOT NULL DEFAULT '[]',
			started_at TEXT NOT NULL,
			finished_at TEXT NOT NULL DEFAULT ''
		)`,
		`CREATE TABLE IF NOT EXISTS schedules (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			workflow_id INTEGER NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			cron_expr TEXT NOT NULL,
			next_run_at TEXT NOT NULL,
			enabled INTEGER NOT NULL DEFAULT 1,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS workspace_files (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			file_path TEXT NOT NULL DEFAULT '',
			size INTEGER NOT NULL DEFAULT 0,
			content_type TEXT NOT NULL DEFAULT 'application/octet-stream',
			status TEXT NOT NULL DEFAULT 'referenced',
			content BLOB,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS webhooks (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			workflow_id INTEGER NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			token TEXT NOT NULL UNIQUE,
			enabled INTEGER NOT NULL DEFAULT 1,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS http_actions (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			url TEXT NOT NULL,
			method TEXT NOT NULL DEFAULT 'GET',
			payload TEXT NOT NULL DEFAULT '',
			last_status INTEGER NOT NULL DEFAULT 0,
			last_response TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS secrets (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			masked_value TEXT NOT NULL,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL,
			UNIQUE(user_id, name)
		)`,
		`CREATE TABLE IF NOT EXISTS run_logs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			run_id INTEGER NOT NULL,
			step_name TEXT NOT NULL,
			status TEXT NOT NULL DEFAULT 'running',
			output TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS templates (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			name TEXT NOT NULL,
			description TEXT NOT NULL DEFAULT '',
			definition_json TEXT NOT NULL DEFAULT '{}',
			is_published INTEGER NOT NULL DEFAULT 0,
			downloads INTEGER NOT NULL DEFAULT 0,
			created_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS governance_settings (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			setting_key TEXT NOT NULL UNIQUE,
			setting_value TEXT NOT NULL,
			updated_by INTEGER NOT NULL,
			updated_at TEXT NOT NULL
		)`,
		`CREATE TABLE IF NOT EXISTS audit_events (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			actor_id INTEGER NOT NULL,
			action TEXT NOT NULL,
			entity_type TEXT NOT NULL DEFAULT '',
			entity_id TEXT NOT NULL DEFAULT '',
			detail TEXT NOT NULL DEFAULT '',
			created_at TEXT NOT NULL
		)`,
		`CREATE INDEX IF NOT EXISTS idx_runs_user ON runs(user_id, status)`,
		`CREATE INDEX IF NOT EXISTS idx_run_logs_run ON run_logs(run_id)`,
		`CREATE INDEX IF NOT EXISTS idx_webhook_token ON webhooks(token)`,
		`CREATE INDEX IF NOT EXISTS idx_schedule_due ON schedules(enabled, next_run_at)`,
	}
	for _, s := range stmts {
		if _, err := db.Exec(s); err != nil {
			return fmt.Errorf("migrate statement failed: %w", err)
		}
	}
	return nil
}

// Reset drops all tables and recreates them, used by the seed/reset command.
func Reset(db *sql.DB) error {
	tables := []string{
		"audit_events", "governance_settings", "templates", "run_logs",
		"secrets", "http_actions", "webhooks", "workspace_files",
		"schedules", "runs", "tools", "workflows", "account_accesses",
		"sessions", "users",
	}
	for _, t := range tables {
		if _, err := db.Exec("DROP TABLE IF EXISTS " + t); err != nil {
			return fmt.Errorf("drop %s: %w", t, err)
		}
	}
	return Migrate(db)
}
