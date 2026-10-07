const bcrypt = require('bcryptjs');
const { db } = require('../db');

function createUser({ username, password, display_name, is_admin = 0 }) {
  const password_hash = bcrypt.hashSync(password, 10);
  const stmt = db.prepare(`
    INSERT INTO users (username, password_hash, display_name, is_admin)
    VALUES (?, ?, ?, ?)
  `);
  const result = stmt.run(username, password_hash, display_name, is_admin ? 1 : 0);
  return findById(result.lastInsertRowid);
}

function findByUsername(username) {
  return db.prepare(`SELECT * FROM users WHERE username = ?`).get(username);
}

function findById(userId) {
  const user = db.prepare(`
    SELECT user_id, username, display_name, created_at, is_admin
    FROM users
    WHERE user_id = ?
  `).get(userId);

  if (!user) {
    return user;
  }

  return {
    ...user,
    is_admin: Boolean(user.is_admin)
  };
}

function findAuthByUsername(username) {
  return db.prepare(`SELECT * FROM users WHERE username = ?`).get(username);
}

function verifyPassword(user, plainPassword) {
  if (!user) return false;
  return bcrypt.compareSync(plainPassword, user.password_hash);
}

function listUsers() {
  return db.prepare(`
    SELECT user_id, username, display_name, created_at, is_admin
    FROM users
    ORDER BY user_id ASC
  `).all().map((user) => ({
    ...user,
    is_admin: Boolean(user.is_admin)
  }));
}

module.exports = {
  createUser,
  findByUsername,
  findById,
  findAuthByUsername,
  verifyPassword,
  listUsers
};