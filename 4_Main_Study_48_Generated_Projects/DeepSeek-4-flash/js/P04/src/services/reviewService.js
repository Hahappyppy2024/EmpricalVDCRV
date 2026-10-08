import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { recordAudit } from './auditService.js';
import { broadcast } from './wsHub.js';

function publicReview(row) {
  return {
    id: row.id,
    booking_id: row.booking_id,
    booking_code: row.code,
    room_name: row.room_name,
    user_id: row.user_id,
    author_name: row.author_name,
    rating: row.rating,
    text: row.text,
    status: row.status,
    moderated_by: row.moderated_by,
    moderated_at: row.moderated_at,
    created_at: row.created_at,
  };
}

const SELECT_REVIEW = `
  SELECT rv.*, b.code, r.name AS room_name, u.name AS author_name
  FROM reviews rv
  JOIN bookings b ON b.id = rv.booking_id
  JOIN rooms r ON r.id = b.room_id
  JOIN users u ON u.id = rv.user_id
`;

export function listReviews(viewer) {
  const db = getDb();
  if (viewer && ['staff', 'admin', 'moderator'].includes(viewer.role)) {
    return db.prepare(`${SELECT_REVIEW} ORDER BY rv.created_at DESC, rv.id DESC`).all().map(publicReview);
  }
  if (viewer) {
    return db.prepare(`
      ${SELECT_REVIEW}
      WHERE rv.status = 'published' OR rv.user_id = ?
      ORDER BY rv.created_at DESC, rv.id DESC
    `).all(viewer.id).map(publicReview);
  }
  return db.prepare(`${SELECT_REVIEW} WHERE rv.status = 'published' ORDER BY rv.created_at DESC, rv.id DESC`).all().map(publicReview);
}

export function createReview({ user_id, booking_id, rating, text }) {
  const db = getDb();
  const booking = db.prepare(`
    SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?
  `).get(booking_id);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
  if (booking.guest_id !== user_id) throw new AppError(403, 'FORBIDDEN', 'You can only review your own stays.');
  if (booking.status !== 'checked_out') {
    throw new AppError(409, 'INVALID_STATE', `Reviews can only be posted for completed stays (current state: ${booking.status}).`);
  }
  const existing = db.prepare('SELECT id FROM reviews WHERE booking_id = ?').get(booking_id);
  if (existing) throw new AppError(409, 'REVIEW_EXISTS', 'A review already exists for this booking.');

  const info = db.prepare(`
    INSERT INTO reviews (booking_id, user_id, rating, text, status)
    VALUES (?, ?, ?, ?, 'pending')
  `).run(booking_id, user_id, rating, text);

  recordAudit({ actorId: user_id, action: 'create', targetType: 'review', targetId: info.lastInsertRowid, details: { booking_id } });
  broadcast('review.created', { review_id: info.lastInsertRowid, booking_id, rating });
  return publicReview(db.prepare(`${SELECT_REVIEW} WHERE rv.id = ?`).get(info.lastInsertRowid));
}

export function moderateReview(reviewId, moderatorId, status) {
  const db = getDb();
  const review = db.prepare(`${SELECT_REVIEW} WHERE rv.id = ?`).get(reviewId);
  if (!review) throw new AppError(404, 'REVIEW_NOT_FOUND', 'Review not found.');
  if (!['published', 'rejected'].includes(status)) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Moderation status must be published or rejected.');
  }
  db.prepare(`
    UPDATE reviews SET status = ?, moderated_by = ?, moderated_at = ? WHERE id = ?
  `).run(status, moderatorId, new Date().toISOString().slice(0, 19).replace('T', ' '), reviewId);
  recordAudit({ actorId: moderatorId, action: 'moderate', targetType: 'review', targetId: reviewId, details: { status } });
  return publicReview(db.prepare(`${SELECT_REVIEW} WHERE rv.id = ?`).get(reviewId));
}

export function reviewStats() {
  const db = getDb();
  return db.prepare(`
    SELECT COUNT(*) AS total, AVG(rating) AS average_rating,
           SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
    FROM reviews
  `).get();
}
