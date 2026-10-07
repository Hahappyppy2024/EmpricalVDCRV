const { db, nowIso } = require('../db');

function createUser({ username, password_hash, display_name }) {
  const info = db.prepare(
    'INSERT INTO users (username, password_hash, display_name, created_at) VALUES (?,?,?,?)'
  ).run(username, password_hash, display_name, nowIso());
  return getById(info.lastInsertRowid);
}

function getById(user_id) {
  return db.prepare('SELECT user_id, username, display_name, created_at FROM users WHERE user_id=?').get(user_id);
}

function getAuthByUsername(username) {
  return db.prepare('SELECT * FROM users WHERE username=?').get(username);
}


const bcrypt = require('bcryptjs');

function getByUsername(username) {
  return db.prepare('SELECT user_id, username, display_name, created_at FROM users WHERE username=?').get(username);
}

function createUserWithPassword({ username, password, display_name }) {
  const hash = bcrypt.hashSync(password, 10);
  const info = db.prepare(
    'INSERT INTO users (username, password_hash, display_name, created_at) VALUES (?,?,?,?)'
  ).run(username, hash, display_name, nowIso());
  return getById(info.lastInsertRowid);
}


function listUsers() {
  return db.prepare('SELECT user_id, username, display_name, created_at FROM users ORDER BY user_id DESC').all();
}

module.exports = { createUser, createUserWithPassword, getById, getByUsername, getAuthByUsername, listUsers };
