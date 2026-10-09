import { getDb } from '../db/connection.js';

export function notify(userId, kind, body) {
  getDb().prepare('INSERT INTO notifications (user_id, kind, body) VALUES (?, ?, ?)').run(userId, kind, body);
}

export function forUser(userId) {
  return getDb().prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100').all(userId);
}

export function markRead(userId, id) {
  getDb().prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?').run(userId, id);
}