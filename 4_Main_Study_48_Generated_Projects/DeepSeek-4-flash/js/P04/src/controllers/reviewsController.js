import { Router } from 'express';
import { listReviews, createReview, moderateReview, reviewStats } from '../services/reviewService.js';
import { requireFields } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';

const router = Router();

// GET /api/hotel/reviews
router.get('/', (req, res) => {
  const reviews = listReviews(req.user);
  const stats = reviewStats();
  res.json({ reviews, stats });
});

// POST /api/hotel/reviews
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, ['booking_id', 'rating', 'text']);
  const rating = Number(req.body.rating);
  if (!Number.isInteger(rating) || rating < 1 || rating > 5) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Rating must be an integer between 1 and 5.');
  }
  const review = createReview({
    user_id: req.user.id,
    booking_id: Number(req.body.booking_id),
    rating,
    text: String(req.body.text).trim(),
  });
  res.status(201).json({ review, message: 'Review submitted for moderation.' });
});

// PATCH /api/hotel/reviews/:id  (moderator moderation)
router.patch('/:id', requireAuth, (req, res) => {
  if (!['moderator', 'admin'].includes(req.user.role)) {
    throw new AppError(403, 'FORBIDDEN', 'Only moderators can moderate reviews.');
  }
  requireFields(req.body, ['status']);
  const review = moderateReview(Number(req.params.id), req.user.id, req.body.status);
  res.json({ review, message: 'Review moderated.' });
});

export default router;
