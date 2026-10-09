import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { badRequest, conflict, notFound, forbidden } from './errors.js';
import { findRoomById, roomAvailability } from './rooms.js';

function generateCode() {
  return 'BK-' + crypto.randomBytes(4).toString('hex').toUpperCase();
}

export function findBookingById(id) {
  return getDb().prepare('SELECT * FROM bookings WHERE id = ?').get(id);
}

export function bookingsForUser(userId) {
  return getDb().prepare(`SELECT b.*, r.name AS room_name, r.code AS room_code
                          FROM bookings b JOIN rooms r ON r.id = b.room_id
                          WHERE b.user_id = ? ORDER BY b.check_in DESC`).all(userId);
}

export function bookingsAll() {
  return getDb().prepare(`SELECT b.*, r.name AS room_name, r.code AS room_code, u.display_name AS guest_name
                          FROM bookings b JOIN rooms r ON r.id = b.room_id
                          JOIN users u ON u.id = b.user_id
                          ORDER BY b.check_in DESC`).all();
}

export function createBooking({ user, room_id, check_in, check_out, guests, card_number, card_holder, notes }) {
  const db = getDb();
  const room = findRoomById(room_id);
  if (!room) throw notFound('Room not found');
  if (room.status !== 'active') throw conflict('Room is not bookable');
  if (Number(guests) > room.capacity) throw badRequest('Guests exceed room capacity');
  const avail = roomAvailability(room_id, check_in, check_out);
  if (!avail.available) throw conflict('Room is not available for the requested dates');

  const nights = Math.max(1, Math.round((Date.parse(check_out) - Date.parse(check_in)) / 86400000));
  const total = nights * room.nightly_rate_cents;
  const last4 = (String(card_number).replace(/\D/g, '')).slice(-4).padStart(4, '0');
  const code = generateCode();
  const tx = db.transaction(() => {
    const info = db.prepare(`INSERT INTO bookings (code, user_id, room_id, check_in, check_out, guests, status, total_cents, payment_status, card_last4, notes)
                             VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, 'paid', ?, ?)`).run(
      code, user.id, room_id, check_in, check_out, Number(guests), total, last4, notes || ''
    );
    db.prepare(`INSERT INTO booking_status_log (booking_id, actor_id, from_status, to_status, reason) VALUES (?, ?, 'pending', 'confirmed', 'payment captured')`).run(info.lastInsertRowid, user.id);
    return info.lastInsertRowid;
  });
  const id = tx();
  return findBookingById(id);
}

const TRANSITIONS = {
  cancel: { from: ['pending', 'confirmed'], to: 'cancelled' },
  modify: { from: ['pending', 'confirmed'], to: 'confirmed' },
};

export function performAction(user, bookingId, action, patch = {}, reason = '') {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (booking.user_id !== user.id) throw forbidden('Cannot modify another user\'s booking');
  const rule = TRANSITIONS[action];
  if (!rule) throw badRequest('Unsupported action');
  if (!rule.from.includes(booking.status)) throw conflict(`Cannot ${action} a booking in status ${booking.status}`);

  if (action === 'modify') {
    const check_in = patch.check_in || booking.check_in;
    const check_out = patch.check_out || booking.check_out;
    if (check_in >= check_out) throw badRequest('check_out must be after check_in');
    const avail = roomAvailability(booking.room_id, check_in, check_out);
    if (!avail.available) throw conflict('Room not available on new dates');
    const room = findRoomById(booking.room_id);
    const nights = Math.max(1, Math.round((Date.parse(check_out) - Date.parse(check_in)) / 86400000));
    const total = nights * room.nightly_rate_cents;
    db.prepare(`UPDATE bookings SET check_in = ?, check_out = ?, guests = ?, total_cents = ?, notes = ?, updated_at = datetime('now') WHERE id = ?`)
      .run(check_in, check_out, patch.guests || booking.guests, total, patch.notes ?? booking.notes, bookingId);
    db.prepare(`INSERT INTO booking_status_log (booking_id, actor_id, from_status, to_status, reason) VALUES (?, ?, ?, ?, ?)`)
      .run(bookingId, user.id, booking.status, 'confirmed', 'modified ' + reason);
    return findBookingById(bookingId);
  }

  if (action === 'cancel') {
    db.prepare(`UPDATE bookings SET status = 'cancelled', payment_status = CASE WHEN payment_status='paid' THEN 'refunded' ELSE payment_status END, updated_at = datetime('now') WHERE id = ?`).run(bookingId);
    db.prepare(`INSERT INTO booking_status_log (booking_id, actor_id, from_status, to_status, reason) VALUES (?, ?, ?, 'cancelled', ?)`)
      .run(bookingId, user.id, booking.status, reason || 'user cancelled');
    return findBookingById(bookingId);
  }
}

export function staffUpdateStatus(actor, bookingId, status, reason = '') {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (!['confirmed', 'checked_in', 'checked_out'].includes(booking.status)) throw conflict(`Cannot update from status ${booking.status}`);
  if (status === 'check_in') {
    if (booking.status !== 'confirmed') throw conflict('Only confirmed bookings can be checked in');
    db.prepare(`UPDATE bookings SET status = 'checked_in', updated_at = datetime('now') WHERE id = ?`).run(bookingId);
  } else if (status === 'check_out') {
    if (booking.status !== 'checked_in') throw conflict('Only checked-in bookings can be checked out');
    db.prepare(`UPDATE bookings SET status = 'checked_out', updated_at = datetime('now') WHERE id = ?`).run(bookingId);
  }
  db.prepare(`INSERT INTO booking_status_log (booking_id, actor_id, from_status, to_status, reason) VALUES (?, ?, ?, ?, ?)`)
    .run(bookingId, actor.id, booking.status, status === 'check_in' ? 'checked_in' : 'checked_out', reason || '');
  return findBookingById(bookingId);
}

export function bookingLog(bookingId) {
  return getDb().prepare(`SELECT l.*, u.display_name AS actor_name FROM booking_status_log l
                          LEFT JOIN users u ON u.id = l.actor_id WHERE booking_id = ? ORDER BY l.id ASC`).all(bookingId);
}