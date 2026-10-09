import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { forbidden, notFound } from './errors.js';
import { findBookingById } from './bookings.js';

export function listMessages(actor, bookingId) {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (actor.role === 'guest' && booking.user_id !== actor.id) throw forbidden('Cannot view other guest messages');
  return db.prepare(`SELECT m.*, u.display_name AS sender_name FROM messages m
                     JOIN users u ON u.id = m.sender_id
                     WHERE booking_id = ? ORDER BY m.id ASC`).all(bookingId);
}

export function postMessage(actor, bookingId, body) {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (actor.role === 'guest' && booking.user_id !== actor.id) throw forbidden('Cannot post on other guest booking');
  const info = db.prepare(`INSERT INTO messages (booking_id, sender_id, sender_role, body) VALUES (?, ?, ?, ?)`)
    .run(bookingId, actor.id, actor.role, String(body).slice(0, 2000));
  return db.prepare(`SELECT m.*, u.display_name AS sender_name FROM messages m
                     JOIN users u ON u.id = m.sender_id WHERE m.id = ?`).get(info.lastInsertRowid);
}