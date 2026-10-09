import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const DB_PATH = process.env.DB_PATH || path.resolve(process.cwd(), 'data', 'app.sqlite');

const dataDir = path.dirname(DB_PATH);
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
}

export const DB_FILE = DB_PATH;

export function createDatabase(targetPath = DB_PATH) {
  const inst = new Database(targetPath);
  inst.pragma('journal_mode = WAL');
  inst.pragma('foreign_keys = ON');
  return inst;
}

export const db = createDatabase();

export const SCHEMA_SQL = `
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('employee','manager','finance','admin')),
    department_id INTEGER,
    manager_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
  );

  CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    code TEXT NOT NULL UNIQUE,
    cost_center TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    code TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS expense_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_code TEXT NOT NULL UNIQUE,
    employee_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    total_amount REAL NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'USD',
    status TEXT NOT NULL DEFAULT 'draft'
      CHECK (status IN ('draft','submitted','manager_approved','manager_rejected','changes_requested','finance_approved','finance_rejected','reimbursed','paid')),
    department_id INTEGER,
    cost_center TEXT,
    submitted_at TEXT,
    manager_decision_at TEXT,
    finance_decision_at TEXT,
    reimbursement_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
  );

  CREATE INDEX IF NOT EXISTS idx_reports_employee ON expense_reports(employee_id);
  CREATE INDEX IF NOT EXISTS idx_reports_status ON expense_reports(status);
  CREATE INDEX IF NOT EXISTS idx_reports_department ON expense_reports(department_id);

  CREATE TABLE IF NOT EXISTS expense_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    category_id INTEGER,
    description TEXT NOT NULL,
    expense_date TEXT NOT NULL,
    amount REAL NOT NULL,
    currency TEXT NOT NULL DEFAULT 'USD',
    merchant TEXT,
    receipt_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (receipt_id) REFERENCES stored_files(id) ON DELETE SET NULL
  );

  CREATE INDEX IF NOT EXISTS idx_lines_report ON expense_lines(report_id);

  CREATE TABLE IF NOT EXISTS stored_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    stored_name TEXT NOT NULL UNIQUE,
    mime_type TEXT NOT NULL,
    size_bytes INTEGER NOT NULL,
    storage_path TEXT NOT NULL,
    context TEXT NOT NULL,
    related_report_id INTEGER,
    related_line_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_report_id) REFERENCES expense_reports(id) ON DELETE SET NULL,
    FOREIGN KEY (related_line_id) REFERENCES expense_lines(id) ON DELETE SET NULL
  );

  CREATE INDEX IF NOT EXISTS idx_files_owner ON stored_files(owner_id);
  CREATE INDEX IF NOT EXISTS idx_files_report ON stored_files(related_report_id);

  CREATE TABLE IF NOT EXISTS policy_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    category_id INTEGER,
    max_amount REAL,
    require_receipt_above REAL NOT NULL DEFAULT 0,
    block_when_exceeded INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
  );

  CREATE TABLE IF NOT EXISTS manager_approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    manager_id INTEGER NOT NULL,
    decision TEXT NOT NULL CHECK (decision IN ('approved','rejected','changes_requested')),
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
  );

  CREATE INDEX IF NOT EXISTS idx_approvals_report ON manager_approvals(report_id);

  CREATE TABLE IF NOT EXISTS finance_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    finance_id INTEGER NOT NULL,
    decision TEXT NOT NULL CHECK (decision IN ('approved','rejected','paid','on_hold')),
    batch_id TEXT,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (finance_id) REFERENCES users(id) ON DELETE CASCADE
  );

  CREATE INDEX IF NOT EXISTS idx_finance_report ON finance_reviews(report_id);

  CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
  );

  CREATE INDEX IF NOT EXISTS idx_comments_report ON comments(report_id);

  CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id INTEGER,
    report_id INTEGER,
    event_type TEXT NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE SET NULL
  );

  CREATE INDEX IF NOT EXISTS idx_activity_report ON activity_logs(report_id);

  CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id INTEGER,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    action TEXT NOT NULL,
    payload TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
  );

  CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_events(entity_type, entity_id);

  CREATE TABLE IF NOT EXISTS reimbursement_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL UNIQUE,
    finance_id INTEGER NOT NULL,
    file_id INTEGER,
    filters TEXT,
    total_amount REAL NOT NULL DEFAULT 0,
    record_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (finance_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES stored_files(id) ON DELETE SET NULL
  );

  CREATE TABLE IF NOT EXISTS admin_configurations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key TEXT NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT,
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
  );
`;

export function initSchema(target = db) {
  target.exec(SCHEMA_SQL);
}

initSchema();
console.log('Schema initialized at', DB_PATH);
