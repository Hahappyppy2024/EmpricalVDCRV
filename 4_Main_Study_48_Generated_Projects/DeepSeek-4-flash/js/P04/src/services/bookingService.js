import crypto from 'node:crypto';
import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { isoDate } from '../db/seed.js';
import { isRoomAvailable, getRoom } from './roomService.js';
import { recordAudit } from './auditService.js';
import { broadcast } from './wsHub.js';
import { nowSql } from './sessionService.js';

export function uid(prefix, length = 8) {
  return `${prefix}-${crypto.randomBytes(length).toString('hex').slice(0, length)}`.toUpperCase();
}

function maskCard(cardNumber) {
  const digits = String(cardNumber).replace(/\D/g, '');
  const tail = digits.slice(-4);
  return `**** ${tail || '0000'}`;
}

function publicBooking(row) {
  if (!row) return null;
  return {
    id: row.id,
    code: row.code,
    room_id: row.room_id,
    room_name: row.room_name,
    room_type: row.room_type,
    guest_id: row.guest_id,
    guest_name: row.guest_name,
    guest_email: row.guest_email,
    check_in_date: row.check_in_date,
    check_out_date: row.check_out_date,
    guests: row.guests,
    contact_name: row.contact_name,
    contact_email: row.contact_email,
    contact_phone: row.contact_phone,
    total_price: row.total_price,
    status: row.status,
    created_at: row.created_at,
    updated_at: row.updated_at,
  };
}

const SELECT_BOOKING = `
  SELECT b.*, r.name AS room_name, r.type AS room_type, r.image_url AS room_image,
         r.price_per_night AS room_price_per_night,
         u.name AS guest_name, u.email AS guest_email
  FROM bookings b
  JOIN rooms r ON r.id = b.room_id
  JOIN users u ON u.id = b.guest_id
`;

export function getBooking(id) {
  const db = getDb();
  return publicBooking(db.prepare(`${SELECT_BOOKING} WHERE b.id = ?`).get(id));
}

export function getBookingByCode(code) {
  const db = getDb();
  return publicBooking(db.prepare(`${SELECT_BOOKING} WHERE b.code = ?`).get(code));
}

export function createBooking({
  guest_id, room_id, check_in_date, check_out_date, guests, contact_name, contact_email, contact_phone,
  card_number, card_expiry, card_cvc, idempotency_key,
}) {
  const db = getDb();

  if (idempotency_key) {
    const existing = db.prepare(`
      SELECT b.id FROM bookings b WHERE b.idempotency_key = ?
    `).get(idempotency_key);
    if (existing) {
      return { booking: getBooking(existing.id), duplicate: true };
    }
  }

  const room = getRoom(room_id);
  if (!room) throw new AppError(404, 'ROOM_NOT_FOUND', 'Room not found.');

  if (!isRoomAvailable(room_id, check_in_date, check_out_date)) {
    throw new AppError(409, 'ROOM_UNAVAILABLE', 'This room is not available for the selected dates.');
  }

  const nights = (Date.parse(check_out_date) - Date.parse(check_in_date)) / 86400000;
  if (nights <= 0) throw new AppError(400, 'VALIDATION_ERROR', 'Check-out date must be after check-in date.');
  const totalPrice = nights * room.price_per_night;

  const code = uid('BK');
  const created = nowSql();
  const info = db.prepare(`
    INSERT INTO bookings (code, guest_id, room_id, check_in_date, check_out_date, guests, contact_name, contact_email, contact_phone, total_price, status, idempotency_key, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?)
  `).run(code, guest_id, room_id, check_in_date, check_out_date, guests, contact_name, contact_email, contact_phone, totalPrice, idempotency_key || null, created, created);

  const bookingId = info.lastInsertRowid;

  db.prepare(`
    INSERT INTO payments (booking_id, amount, method, card_masked, status, transaction_code)
    VALUES (?, ?, 'card', ?, 'paid', ?)
  `).run(bookingId, totalPrice, maskCard(card_number), uid('TXN'));

  db.prepare(`
    INSERT INTO outbound_notifications (recipient, subject, body) VALUES (?, ?, ?)
  `).run(contact_email, `Booking ${code} confirmed`, `Your stay is confirmed for ${check_in_date} to ${check_out_date} at ${room.name}. Total: $${totalPrice}.`);

  recordAudit({ actorId: guest_id, action: 'create', targetType: 'booking', targetId: bookingId, details: { code } });
  broadcast('booking.created', { booking_id: bookingId, code, room_id, guest_id, check_in_date, check_out_date, total_price: totalPrice });

  return { booking: getBooking(bookingId), duplicate: false };
}

export function listBookingsByGuest(guestId) {
  const db = getDb();
  return db.prepare(`${SELECT_BOOKING} WHERE b.guest_id = ? ORDER BY b.check_in_date DESC, b.id DESC`).all(guestId).map(publicBooking);
}

export function listStaffBookings({ status, date_from, date_to } = {}) {
  const db = getDb();
  let sql = `${SELECT_BOOKING} WHERE 1 = 1`;
  const params = [];
  if (status) {
    sql += ' AND b.status = ?';
    params.push(status);
  }
  if (date_from) {
    sql += ' AND b.check_in_date >= ?';
    params.push(date_from);
  }
  if (date_to) {
    sql += ' AND b.check_in_date <= ?';
    params.push(date_to);
  }
  sql += ' ORDER BY b.check_in_date ASC, b.id ASC';
  return db.prepare(sql).all(...params).map(publicBooking);
}

export function modifyBooking(bookingId, actorId, { check_in_date, check_out_date, guests, contact_phone, contact_name }) {
  const db = getDb();
  const booking = db.prepare(`${SELECT_BOOKING} WHERE b.id = ?`).get(bookingId);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (booking.guest_id !== actorId) throw new AppError(403, 'FORBIDDEN', 'You can only modify your own bookings.');
  if (booking.status !== 'confirmed' && booking.status !== 'checked_in') {
    throw new AppError(409, 'INVALID_STATE', `Bookings in the "${booking.status}" state cannot be modified.`);
  }

  const newCheckIn = check_in_date || booking.check_in_date;
  const newCheckOut = check_out_date || booking.check_out_date;
  const newGuests = guests || booking.guests;

  if (!isRoomAvailable(booking.room_id, newCheckIn, newCheckOut, bookingId)) {
    throw new AppError(409, 'ROOM_UNAVAILABLE', 'The room is not available for the requested new dates.');
  }

  const nights = (Date.parse(newCheckOut) - Date.parse(newCheckIn)) / 86400000;
  if (nights <= 0) throw new AppError(400, 'VALIDATION_ERROR', 'Check-out date must be after check-in date.');
  const totalPrice = nights * booking.room_price_per_night;

  db.prepare(`
    UPDATE bookings SET check_in_date = ?, check_out_date = ?, guests = ?, contact_phone = ?, contact_name = ?, total_price = ?, updated_at = ?
    WHERE id = ?
  `).run(newCheckIn, newCheckOut, newGuests, contact_phone ?? booking.contact_phone, contact_name ?? booking.contact_name, totalPrice, nowSql(), bookingId);

  recordAudit({ actorId, action: 'update', targetType: 'booking', targetId: bookingId, details: { code: booking.code } });
  broadcast('booking.updated', { booking_id: bookingId, code: booking.code, status: booking.status });
  return getBooking(bookingId);
}

export function cancelBooking(bookingId, actorId) {
  const db = getDb();
  const booking = db.prepare(`${SELECT_BOOKING} WHERE b.id = ?`).get(bookingId);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (booking.status !== 'confirmed') {
    throw new AppError(409, 'INVALID_STATE', `Only confirmed bookings can be cancelled (current state: ${booking.status}).`);
  }
  db.prepare(`UPDATE bookings SET status = 'cancelled', updated_at = ? WHERE id = ?`).run(nowSql(), bookingId);
  recordAudit({ actorId, action: 'cancel', targetType: 'booking', targetId: bookingId, details: { code: booking.code } });
  broadcast('booking.cancelled', { booking_id: bookingId, code: booking.code });
  return getBooking(bookingId);
}

export function checkInBooking(bookingId, actorId) {
  const db = getDb();
  const booking = db.prepare(`${SELECT_BOOKING} WHERE b.id = ?`).get(bookingId);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (booking.status !== 'confirmed') {
    throw new AppError(409, 'INVALID_STATE', `Only confirmed bookings can be checked in (current state: ${booking.status}).`);
  }
  db.prepare(`UPDATE bookings SET status = 'checked_in', updated_at = ? WHERE id = ?`).run(nowSql(), bookingId);
  db.prepare('INSERT INTO check_in_out_events (booking_id, actor_id, action) VALUES (?, ?, ?)').run(bookingId, actorId, 'check_in');
  recordAudit({ actorId, action: 'check_in', targetType: 'booking', targetId: bookingId, details: { code: booking.code } });
  broadcast('booking.checked_in', { booking_id: bookingId, code: booking.code });
  return getBooking(bookingId);
}

export function checkOutBooking(bookingId, actorId) {
  const db = getDb();
  const booking = db.prepare(`${SELECT_BOOKING} WHERE b.id = ?`).get(bookingId);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (booking.status !== 'checked_in') {
    throw new AppError(409, 'INVALID_STATE', `Only checked-in bookings can be checked out (current state: ${booking.status}).`);
  }
  db.prepare(`UPDATE bookings SET status = 'checked_out', updated_at = ? WHERE id = ?`).run(nowSql(), bookingId);
  db.prepare('INSERT INTO check_in_out_events (booking_id, actor_id, action) VALUES (?, ?, ?)').run(bookingId, actorId, 'check_out');
  recordAudit({ actorId, action: 'check_out', targetType: 'booking', targetId: bookingId, details: { code: booking.code } });
  broadcast('booking.checked_out', { booking_id: bookingId, code: booking.code });
  return getBooking(bookingId);
}

export function listCheckInOutEvents() {
  const db = getDb();
  return db.prepare(`
    SELECT e.*, b.code, b.status AS booking_status, u.name AS actor_name
    FROM check_in_out_events e
    JOIN bookings b ON b.id = e.booking_id
    JOIN users u ON u.id = e.actor_id
    ORDER BY e.created_at DESC, e.id DESC
    LIMIT 100
  `).all();
}

export function findConflictRoom(checkIn, checkOut) {
  // Deterministic conflict for the frontend integration demo:
  // returns the room that is already booked (if any) for the requested span.
  const db = getDb();
  const row = db.prepare(`
    SELECT b.room_id, r.name AS room_name, b.check_in_date, b.check_out_date
    FROM bookings b JOIN rooms r ON r.id = b.room_id
    WHERE b.status != 'cancelled' AND b.check_in_date < ? AND b.check_out_date > ?
    ORDER BY b.id ASC LIMIT 1
  `).get(checkOut, checkIn);
  return row || null;
}

export { isoDate };
