const bcrypt = require('bcryptjs');
const { db } = require('../db');

function createUser({ username, password, display_name }) {
  const password_hash = bcrypt.hashSync(password, 10);
  const stmt = db.prepare(`
    INSERT INTO users (username, password_hash, display_name)
    VALUES (?, ?, ?)
  `);
  const result = stmt.run(username, password_hash, display_name);
  return findById(result.lastInsertRowid);
}

function findByUsername(username) {
  return db.prepare(`SELECT * FROM users WHERE username = ?`).get(username);
}

function findById(userId) {
  return db.prepare(`
    SELECT user_id, username, display_name, created_at
    FROM users
    WHERE user_id = ?
  `).get(userId);
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
    SELECT user_id, username, display_name, created_at
    FROM users
    ORDER BY user_id ASC
  `).all();
}

module.exports = {
  createUser,
  findByUsername,
  findById,
  findAuthByUsername,
  verifyPassword,
  listUsers
};
