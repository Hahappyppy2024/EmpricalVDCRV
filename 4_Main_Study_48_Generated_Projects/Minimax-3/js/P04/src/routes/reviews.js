import { listReviews, createReview, moderateReview, reviewsByBooking } from '../services/reviews.js';
import { validateReview, validateReviewModeration } from '../services/validate.js';
import { record } from '../services/audit.js';
import { notify } from '../services/notify.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerReviewsRoutes(app) {
  app.get('/api/hotel/reviews', asyncRoute(async (req, res) => {
    const includePending = req.session && (req.session.user.role === 'moderator' || req.session.user.role === 'admin');
    const bookingId = req.query.booking_id ? Number(req.query.booking_id) : null;
    if (bookingId) {
      const reviews = reviewsByBooking(bookingId);
      return sendJson(res, 200, { reviews });
    }
    const reviews = listReviews(includePending);
    sendJson(res, 200, { reviews });
  }));

  app.post('/api/hotel/reviews', requireRole('guest'), asyncRoute(async (req, res) => {
    const errs = validateReview(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const review = createReview(req.session.user, req.body);
      record({ actor: req.session.user, action: 'review.create', targetType: 'review', targetId: review.id });
      notify(req.session.user.id, 'review', 'Your review is awaiting moderation.');
      sendJson(res, 201, { review });
    } catch (e) {
      if (e.code === 'conflict') return sendJson(res, 409, { error: e.code, message: e.message });
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      if (e.code === 'validation_error') return sendJson(res, 400, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/reviews/:id', requireRole('moderator', 'admin'), asyncRoute(async (req, res) => {
    const errs = validateReviewModeration(Object.assign({ review_id: req.params.id }, req.body || {}));
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const review = moderateReview(req.session.user, Number(req.params.id), req.body.decision, req.body.reason || '');
      record({ actor: req.session.user, action: `review.${review.status}`, targetType: 'review', targetId: review.id });
      sendJson(res, 200, { review });
    } catch (e) {
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'validation_error') return sendJson(res, 400, { error: e.code, message: e.message });
      throw e;
    }
  }));
}