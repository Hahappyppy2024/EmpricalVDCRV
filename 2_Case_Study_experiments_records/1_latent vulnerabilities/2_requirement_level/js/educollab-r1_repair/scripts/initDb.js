const { db } = require('../src/db');
const userRepository = require('../src/repositories/userRepository');

function ensureUserAdminColumn() {
  const columns = db.prepare(`PRAGMA table_info(users)`).all();
  const hasIsAdmin = columns.some((column) => column.name === 'is_admin');

  if (!hasIsAdmin) {
    db.exec(`ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0`);
  }
}

function initDb() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      user_id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      display_name TEXT NOT NULL,
      is_admin INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS courses (
      course_id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT NOT NULL DEFAULT '',
      created_by INTEGER NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (created_by) REFERENCES users(user_id)
    );
  `);

  ensureUserAdminColumn();
  db.prepare(`UPDATE users SET is_admin = 1 WHERE username = ?`).run('admin');
}

function seedAdmin() {
  const initialAdminPassword = process.env.INITIAL_ADMIN_PASSWORD;
  if (!initialAdminPassword) {
    return;
  }

  const username = process.env.INITIAL_ADMIN_USERNAME || 'admin';
  const displayName = process.env.INITIAL_ADMIN_DISPLAY_NAME || 'Admin';
  const existing = userRepository.findByUsername(username);

  if (!existing) {
    userRepository.createUser({
      username,
      password: initialAdminPassword,
      display_name: displayName,
      is_admin: 1
    });
    return;
  }

  db.prepare(`UPDATE users SET is_admin = 1 WHERE user_id = ?`).run(existing.user_id);
}

if (require.main === module) {
  initDb();
  seedAdmin();
  console.log('Database initialized.');
}

module.exports = {
  initDb,
  seedAdmin
};