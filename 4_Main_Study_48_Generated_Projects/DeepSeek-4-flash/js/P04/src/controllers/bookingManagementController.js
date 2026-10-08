import { Router } from 'express';
import { getBooking, listBookingsByGuest, cancelBooking, modifyBooking, listStaffBookings } from '../services/bookingService.js';
import { listAudit } from '../services/auditService.js';
import { requireFields, isIsoDate } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';

const router = Router();

// GET /api/hotel/booking_management
router.get('/', requireAuth, (req, res) => {
  const isStaff = ['staff', 'admin'].includes(req.user.role);
  const bookings = isStaff ? listStaffBookings(req.query) : listBookingsByGuest(req.user.id);
  const audit = isStaff ? listAudit({ limit: 20 }) : listAudit({ actorId: req.user.id, limit: 20 });
  res.json({ bookings, audit_trail: audit });
});

// POST /api/hotel/booking_management
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, ['action']);
  const { action, booking_id } = req.body;
  if (action === 'cancel') {
    requireFields(req.body, ['booking_id']);
    const id = Number(booking_id);
    const booking = getBooking(id);
    if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');
    if (req.user.role === 'guest' && booking.guest_id !== req.user.id) {
      throw new AppError(403, 'FORBIDDEN', 'You can only cancel your own bookings.');
    }
    const cancelled = cancelBooking(id, req.user.id);
    return res.json({ booking: cancelled, message: 'Booking cancelled.' });
  }
  throw new AppError(400, 'VALIDATION_ERROR', 'Unknown booking management action.');
});

// PATCH /api/hotel/booking_management/:id
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const booking = getBooking(id);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');

  const { action } = req.body;
  if (action === 'cancel') {
    if (req.user.role === 'guest' && booking.guest_id !== req.user.id) {
      throw new AppError(403, 'FORBIDDEN', 'You can only cancel your own bookings.');
    }
    return res.json({ booking: cancelBooking(id, req.user.id), message: 'Booking cancelled.' });
  }

  if (action === 'modify') {
    if (booking.guest_id !== req.user.id) {
      throw new AppError(403, 'FORBIDDEN', 'You can only modify your own bookings.');
    }
    const patch = {
      check_in_date: req.body.check_in_date,
      check_out_date: req.body.check_out_date,
      guests: req.body.guests ? Number(req.body.guests) : undefined,
      contact_phone: req.body.contact_phone,
      contact_name: req.body.contact_name,
    };
    if (patch.check_in_date && !isIsoDate(patch.check_in_date)) throw new AppError(400, 'VALIDATION_ERROR', 'check_in_date must be a valid date.');
    if (patch.check_out_date && !isIsoDate(patch.check_out_date)) throw new AppError(400, 'VALIDATION_ERROR', 'check_out_date must be a valid date.');
    const updated = modifyBooking(id, req.user.id, patch);
    return res.json({ booking: updated, message: 'Booking modified.' });
  }

  throw new AppError(400, 'VALIDATION_ERROR', 'Unknown booking management patch action.');
});

export default router;
