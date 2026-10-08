import { Router } from 'express';
import { checkInBooking, checkOutBooking, listStaffBookings, listCheckInOutEvents } from '../services/bookingService.js';
import { requireFields } from '../middleware/validation.js';
import { requireRole } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { isoDate } from '../db/seed.js';

const router = Router();

// GET /api/hotel/staff_check_in_out
router.get('/', requireRole('staff', 'admin'), (req, res) => {
  const dueForCheckIn = listStaffBookings({ status: 'confirmed', date_to: isoDate(30) });
  const dueForCheckOut = listStaffBookings({ status: 'checked_in' });
  const events = listCheckInOutEvents();
  res.json({ due_for_check_in: dueForCheckIn, due_for_check_out: dueForCheckOut, events });
});

function runAction(bookingId, action, actorId) {
  if (action === 'check_in') {
    return { booking: checkInBooking(bookingId, actorId), message: 'Guest checked in.' };
  }
  if (action === 'check_out') {
    return { booking: checkOutBooking(bookingId, actorId), message: 'Guest checked out.' };
  }
  throw new AppError(400, 'VALIDATION_ERROR', 'Action must be check_in or check_out.');
}

// POST /api/hotel/staff_check_in_out
router.post('/', requireRole('staff', 'admin'), (req, res) => {
  requireFields(req.body, ['booking_id']);
  res.status(200).json(runAction(Number(req.body.booking_id), req.body.action, req.user.id));
});

// PATCH /api/hotel/staff_check_in_out/:id
router.patch('/:id', requireRole('staff', 'admin'), (req, res) => {
  const bookingId = Number(req.params.id);
  res.status(200).json(runAction(bookingId, req.body.action, req.user.id));
});

export default router;
