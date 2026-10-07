const path = require('path');
const Database = require('better-sqlite3');

let _db = null;

function getDb() {
  if (_db) return _db;

  const dbPath = process.env.DB_PATH || path.join(__dirname, '..', 'data', 'app.db');
  _db = new Database(dbPath);
  _db.pragma('foreign_keys = ON');
  console.log('[DB] connected to =', dbPath);
  return _db;
}

// 兼容旧代码：require { db } 仍然可用（但推荐改成 getDb()）
const dbProxy = new Proxy(
  {},
  {
    get(_target, prop) {
      const db = getDb();
      const v = db[prop];
      return typeof v === 'function' ? v.bind(db) : v;
    },
  }
);

function ensureColumn(db, table, column, typeSql) {
  const cols = db.prepare(`PRAGMA table_info(${table})`).all().map(r => r.name);
  if (!cols.includes(column)) {
    db.prepare(`ALTER TABLE ${table} ADD COLUMN ${column} ${typeSql}`).run();
  }
}

function initDb() {
  const db = getDb();

  // Users
  db.prepare(`
    CREATE TABLE IF NOT EXISTS users (
      user_id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      display_name TEXT NOT NULL,
      created_at TEXT NOT NULL
    )
  `).run();

  // Courses
  db.prepare(`
    CREATE TABLE IF NOT EXISTS courses (
      course_id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT NOT NULL,
      created_by INTEGER NOT NULL,
      created_at TEXT NOT NULL,
      FOREIGN KEY(created_by) REFERENCES users(user_id)
    )
  `).run();

  // Membership
  db.prepare(`
    CREATE TABLE IF NOT EXISTS memberships (
      membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      role_in_course TEXT NOT NULL,
      created_at TEXT NOT NULL,
      UNIQUE(course_id, user_id),
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
  `).run();

  // Invite tokens (course join links) — keep ONE canonical schema
  db.prepare(`
    CREATE TABLE IF NOT EXISTS invite_tokens (
      invite_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      role_in_course TEXT NOT NULL,
      token_hash TEXT NOT NULL UNIQUE,
      created_by INTEGER NOT NULL,
      created_at TEXT NOT NULL,
      expires_at TEXT NOT NULL,
      used_at TEXT,
      used_by INTEGER,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(created_by) REFERENCES users(user_id) ON DELETE CASCADE,
      FOREIGN KEY(used_by) REFERENCES users(user_id) ON DELETE SET NULL
    )
  `).run();

  db.prepare(`CREATE INDEX IF NOT EXISTS idx_invite_tokens_course ON invite_tokens(course_id)`).run();
  db.prepare(`CREATE INDEX IF NOT EXISTS idx_invite_tokens_hash ON invite_tokens(token_hash)`).run();

  // Posts
  db.prepare(`
    CREATE TABLE IF NOT EXISTS posts (
      post_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      author_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(author_id) REFERENCES users(user_id)
    )
  `).run();

  // Comments
  db.prepare(`
    CREATE TABLE IF NOT EXISTS comments (
      comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
      post_id INTEGER NOT NULL,
      course_id INTEGER NOT NULL,
      author_id INTEGER NOT NULL,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(author_id) REFERENCES users(user_id)
    )
  `).run();

  // Assignments
  db.prepare(`
    CREATE TABLE IF NOT EXISTS assignments (
      assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      created_by INTEGER NOT NULL,
      title TEXT NOT NULL,
      description TEXT NOT NULL,
      due_at TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(created_by) REFERENCES users(user_id)
    )
  `).run();

  // Uploads
  db.prepare(`
    CREATE TABLE IF NOT EXISTS uploads (
      upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      uploaded_by INTEGER NOT NULL,
      original_name TEXT NOT NULL,
      storage_path TEXT NOT NULL,
      created_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(uploaded_by) REFERENCES users(user_id)
    )
  `).run();

  // Submissions
  db.prepare(`
    CREATE TABLE IF NOT EXISTS submissions (
      submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
      assignment_id INTEGER NOT NULL,
      course_id INTEGER NOT NULL,
      student_id INTEGER NOT NULL,
      content_text TEXT NOT NULL,
      attachment_upload_id INTEGER,
      score INTEGER,
      feedback TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      UNIQUE(assignment_id, student_id),
      FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(student_id) REFERENCES users(user_id),
      FOREIGN KEY(attachment_upload_id) REFERENCES uploads(upload_id) ON DELETE SET NULL
    )
  `).run();

  // Questions
  db.prepare(`
    CREATE TABLE IF NOT EXISTS questions (
      question_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      created_by INTEGER NOT NULL,
      qtype TEXT NOT NULL,
      prompt TEXT NOT NULL,
      options_json TEXT,
      answer_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(created_by) REFERENCES users(user_id)
    )
  `).run();

  // Quizzes
  db.prepare(`
    CREATE TABLE IF NOT EXISTS quizzes (
      quiz_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      created_by INTEGER NOT NULL,
      title TEXT NOT NULL,
      description TEXT NOT NULL,
      open_at TEXT NOT NULL,
      due_at TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(created_by) REFERENCES users(user_id)
    )
  `).run();

  // QuizQuestion
  db.prepare(`
    CREATE TABLE IF NOT EXISTS quiz_questions (
      quiz_id INTEGER NOT NULL,
      question_id INTEGER NOT NULL,
      points INTEGER NOT NULL,
      position INTEGER NOT NULL,
      PRIMARY KEY(quiz_id, question_id),
      FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
      FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )
  `).run();

  // QuizAttempt
  db.prepare(`
    CREATE TABLE IF NOT EXISTS quiz_attempts (
      attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
      quiz_id INTEGER NOT NULL,
      course_id INTEGER NOT NULL,
      student_id INTEGER NOT NULL,
      started_at TEXT NOT NULL,
      submitted_at TEXT,
      FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
      FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
      FOREIGN KEY(student_id) REFERENCES users(user_id)
    )
  `).run();

  // QuizAnswer
  db.prepare(`
    CREATE TABLE IF NOT EXISTS quiz_answers (
      answer_id INTEGER PRIMARY KEY AUTOINCREMENT,
      attempt_id INTEGER NOT NULL,
      question_id INTEGER NOT NULL,
      answer_json TEXT NOT NULL,
      created_at TEXT NOT NULL,
      UNIQUE(attempt_id, question_id),
      FOREIGN KEY(attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
      FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )
  `).run();

  // Audit Log (A09)
  db.prepare(`
    CREATE TABLE IF NOT EXISTS audit_log (
      audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
      actor_user_id INTEGER,
      actor_username TEXT,
      action TEXT NOT NULL,
      target_type TEXT,
      target_id INTEGER,
      meta_json TEXT,
      created_at TEXT NOT NULL,
      FOREIGN KEY(actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL
    )
  `).run();

  // Migrations for older DBs
  ensureColumn(db, 'submissions', 'score', 'INTEGER');
  ensureColumn(db, 'submissions', 'feedback', 'TEXT');

  // sanity check (optional; keep for debugging)
  const chk = db.prepare(
    "SELECT name FROM sqlite_master WHERE type='table' AND name='invite_tokens'"
  ).get();
  console.log('[DB] invite_tokens exists?', !!chk);
}

function nowIso() {
  return new Date().toISOString();
}

function seedAdmin() {
  const db = getDb();
  const bcrypt = require('bcryptjs');
  const username = 'admin';
  const password = 'admin123';

  const existing = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  if (!existing) {
    const hash = bcrypt.hashSync(password, 10);
    db.prepare(
      'INSERT INTO users (username, password_hash, display_name, created_at) VALUES (?,?,?,?)'
    ).run(username, hash, 'Admin', nowIso());
    console.log('Seeded default admin: admin / admin123');
  }
}

module.exports = {
  // recommended
  getDb,
  initDb,
  seedAdmin,
  nowIso,

  // compatibility (legacy imports)
  db: dbProxy,
};