import { getDb } from '../db/connection.js';
import { badRequest, conflict, forbidden, notFound } from './errors.js';
import { findBookingById } from './bookings.js';

export function listReviews(includePending = false) {
  const db = getDb();
  const sql = includePending
    ? `SELECT r.*, u.display_name AS author FROM reviews r JOIN users u ON u.id = r.user_id ORDER BY r.id DESC`
    : `SELECT r.*, u.display_name AS author FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.status = 'approved' ORDER BY r.id DESC`;
  return db.prepare(sql).all();
}

export function reviewsByBooking(bookingId) {
  return getDb().prepare('SELECT * FROM reviews WHERE booking_id = ? ORDER BY id DESC').all(bookingId);
}

export function createReview(user, { booking_id, rating, body }) {
  const db = getDb();
  const booking = findBookingById(booking_id);
  if (!booking) throw notFound('Booking not found');
  if (booking.user_id !== user.id) throw forbidden('Cannot review another guest\'s booking');
  if (booking.status !== 'checked_out') throw badRequest('Only completed stays can be reviewed');
  const existing = db.prepare('SELECT id FROM reviews WHERE booking_id = ? AND user_id = ?').get(booking_id, user.id);
  if (existing) throw conflict('You already reviewed this stay');
  const info = db.prepare(`INSERT INTO reviews (booking_id, user_id, rating, body, status) VALUES (?, ?, ?, ?, 'pending')`).run(booking_id, user.id, rating, String(body).slice(0, 2000));
  return db.prepare('SELECT * FROM reviews WHERE id = ?').get(info.lastInsertRowid);
}

export function moderateReview(moderator, reviewId, decision, reason = '') {
  const db = getDb();
  const review = db.prepare('SELECT * FROM reviews WHERE id = ?').get(reviewId);
  if (!review) throw notFound('Review not found');
  if (review.status !== 'pending') throw badRequest(`Review already ${review.status}`);
  const newStatus = decision === 'approve' ? 'approved' : 'rejected';
  db.prepare(`UPDATE reviews SET status = ? WHERE id = ?`).run(newStatus, reviewId);
  db.prepare(`INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?, ?)`).run(
    moderator.id, moderator.role, `review.${newStatus}`, 'review', reviewId, reason
  );
  return db.prepare('SELECT * FROM reviews WHERE id = ?').get(reviewId);
}