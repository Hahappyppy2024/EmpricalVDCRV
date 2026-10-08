import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { recordAudit } from './auditService.js';
import { broadcast } from './wsHub.js';

function publicMessage(row) {
  return {
    id: row.id,
    booking_id: row.booking_id,
    booking_code: row.code,
    user_id: row.user_id,
    author_name: row.author_name,
    sender_role: row.sender_role,
    body: row.body,
    created_at: row.created_at,
  };
}

function conversations(bookingRows) {
  const db = getDb();
  return bookingRows.map((b) => ({
    booking_id: b.id,
    booking_code: b.code,
    room_name: b.room_name,
    check_in_date: b.check_in_date,
    check_out_date: b.check_out_date,
    guest_name: b.guest_name,
    guest_id: b.guest_id,
    status: b.status,
    messages: db.prepare(`
      SELECT m.*, u.name AS author_name, b.code
      FROM messages m
      JOIN users u ON u.id = m.user_id
      JOIN bookings b ON b.id = m.booking_id
      WHERE m.booking_id = ?
      ORDER BY m.created_at ASC, m.id ASC
    `).all(b.id).map(publicMessage),
  }));
}

export function listForGuest(guestId) {
  const db = getDb();
  const bookings = db.prepare(`
    SELECT b.id, b.code, r.name AS room_name, b.check_in_date, b.check_out_date, b.status, u.name AS guest_name, u.id AS guest_id
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN users u ON u.id = b.guest_id
    WHERE b.guest_id = ?
    ORDER BY b.check_in_date DESC
  `).all(guestId);
  return conversations(bookings);
}

export function listForStaff() {
  const db = getDb();
  const bookings = db.prepare(`
    SELECT b.id, b.code, r.name AS room_name, b.check_in_date, b.check_out_date, b.status, u.name AS guest_name, u.id AS guest_id
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN users u ON u.id = b.guest_id
    ORDER BY b.check_in_date DESC
  `).all();
  return conversations(bookings);
}

export function sendMessage({ booking_id, user_id, role, body }) {
  const db = getDb();
  const booking = db.prepare(`
    SELECT b.*, u.email AS guest_email FROM bookings b JOIN users u ON u.id = b.guest_id WHERE b.id = ?
  `).get(booking_id);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (role === 'guest' && booking.guest_id !== user_id) {
    throw new AppError(403, 'FORBIDDEN', 'You can only message about your own bookings.');
  }
  if (role === 'guest' && booking.status === 'cancelled') {
    throw new AppError(409, 'INVALID_STATE', 'A cancelled booking cannot receive new messages.');
  }
  const senderRole = role;
  const info = db.prepare(`
    INSERT INTO messages (booking_id, user_id, body, sender_role)
    VALUES (?, ?, ?, ?)
  `).run(booking_id, user_id, body, senderRole);

  db.prepare(`
    INSERT INTO outbound_notifications (recipient, subject, body) VALUES (?, ?, ?)
  `).run(booking.guest_email, `New message on booking ${booking.code}`, body);

  const row = db.prepare(`
    SELECT m.*, u.name AS author_name, b.code FROM messages m
    JOIN users u ON u.id = m.user_id
    JOIN bookings b ON b.id = m.booking_id
    WHERE m.id = ?
  `).get(info.lastInsertRowid);

  recordAudit({ actorId: user_id, action: 'message', targetType: 'booking', targetId: booking_id, details: { body } });
  broadcast('message.created', { booking_id, message_id: row.id, author_name: row.author_name, sender_role: senderRole, body });
  return publicMessage(row);
}

export function updateMessage(messageId, userId, body) {
  const db = getDb();
  const msg = db.prepare('SELECT * FROM messages WHERE id = ?').get(messageId);
  if (!msg) throw new AppError(404, 'MESSAGE_NOT_FOUND', 'Message not found.');
  if (msg.user_id !== userId) throw new AppError(403, 'FORBIDDEN', 'You can only edit your own messages.');
  db.prepare('UPDATE messages SET body = ? WHERE id = ?').run(body, messageId);
  const row = db.prepare(`
    SELECT m.*, u.name AS author_name, b.code FROM messages m
    JOIN users u ON u.id = m.user_id
    JOIN bookings b ON b.id = m.booking_id
    WHERE m.id = ?
  `).get(messageId);
  return publicMessage(row);
}
