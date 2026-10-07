const { db } = require('../src/db');
const userRepository = require('../src/repositories/userRepository');

function initDb() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      user_id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      display_name TEXT NOT NULL,
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

    CREATE TABLE IF NOT EXISTS memberships (
      membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      role_in_course TEXT NOT NULL CHECK (role_in_course IN ('admin', 'teacher', 'student', 'assistant', 'senior-assistant')),
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(course_id, user_id),
      FOREIGN KEY (course_id) REFERENCES courses(course_id),
      FOREIGN KEY (user_id) REFERENCES users(user_id)
    );
  `);
}

function seedAdmin() {
  const existing = userRepository.findByUsername('admin');
  if (!existing) {
    userRepository.createUser({
      username: 'admin',
      password: 'adminpass',
      display_name: 'Admin'
    });
  }
}

if (require.main === module) {
  initDb();
  seedAdmin();
  console.log('Database initialized and admin seeded.');
}

module.exports = {
  initDb,
  seedAdmin
};
