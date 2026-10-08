PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS departments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS cost_centers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  department_id INTEGER REFERENCES departments(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  requires_receipt INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('employee','manager','finance','admin')),
  department_id INTEGER REFERENCES departments(id),
  cost_center_id INTEGER REFERENCES cost_centers(id),
  manager_id INTEGER REFERENCES users(id),
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  expires_at TEXT NOT NULL,
  ip TEXT,
  user_agent TEXT
);

CREATE TABLE IF NOT EXISTS stored_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  size INTEGER NOT NULL,
  owner_id INTEGER NOT NULL REFERENCES users(id),
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  path TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS expense_reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_no TEXT NOT NULL UNIQUE,
  employee_id INTEGER NOT NULL REFERENCES users(id),
  title TEXT NOT NULL,
  purpose TEXT,
  department_id INTEGER REFERENCES departments(id),
  cost_center_id INTEGER REFERENCES cost_centers(id),
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','submitted','approved','rejected','changes_requested','finance_approved','finance_rejected','reimbursed')),
  total_amount REAL NOT NULL DEFAULT 0,
  submitted_at TEXT,
  submitted_to_id INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_reports_status ON expense_reports(status);
CREATE INDEX IF NOT EXISTS idx_reports_employee ON expense_reports(employee_id);
CREATE INDEX IF NOT EXISTS idx_reports_submitted_to ON expense_reports(submitted_to_id);

CREATE TABLE IF NOT EXISTS expense_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER NOT NULL REFERENCES expense_reports(id) ON DELETE CASCADE,
  category_id INTEGER NOT NULL REFERENCES categories(id),
  expense_date TEXT NOT NULL,
  description TEXT NOT NULL,
  merchant TEXT,
  amount REAL NOT NULL,
  receipt_id INTEGER REFERENCES stored_files(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_lines_report ON expense_lines(report_id);

CREATE TABLE IF NOT EXISTS receipts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER REFERENCES expense_reports(id),
  line_id INTEGER REFERENCES expense_lines(id),
  file_id INTEGER NOT NULL REFERENCES stored_files(id),
  uploaded_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS approvals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER NOT NULL REFERENCES expense_reports(id),
  decision TEXT NOT NULL CHECK (decision IN ('approved','rejected','changes_requested')),
  comment TEXT,
  decided_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS finance_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER NOT NULL REFERENCES expense_reports(id),
  decision TEXT NOT NULL CHECK (decision IN ('approved','rejected','reimbursed')),
  comment TEXT,
  decided_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER NOT NULL REFERENCES expense_reports(id),
  author_id INTEGER NOT NULL REFERENCES users(id),
  body TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS activity_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER REFERENCES expense_reports(id),
  actor_id INTEGER REFERENCES users(id),
  type TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS policy_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  rule_type TEXT NOT NULL CHECK (rule_type IN ('max_report_amount','requires_receipt','category_limit','approval_threshold')),
  category_id INTEGER REFERENCES categories(id),
  amount_threshold REAL,
  requires_receipt INTEGER,
  enabled INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS export_batches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  batch_no TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'generated',
  file_id INTEGER REFERENCES stored_files(id),
  created_by INTEGER NOT NULL REFERENCES users(id),
  row_count INTEGER NOT NULL DEFAULT 0,
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id INTEGER REFERENCES users(id),
  action TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  details TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS employee_data_accesses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  viewer_id INTEGER NOT NULL REFERENCES users(id),
  subject_id INTEGER NOT NULL REFERENCES users(id),
  report_id INTEGER NOT NULL REFERENCES expense_reports(id),
  action TEXT NOT NULL,
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (viewer_id, report_id)
);

CREATE TABLE IF NOT EXISTS client_error_states (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id),
  code TEXT NOT NULL,
  message TEXT NOT NULL,
  path TEXT,
  payload TEXT,
  acknowledged INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
