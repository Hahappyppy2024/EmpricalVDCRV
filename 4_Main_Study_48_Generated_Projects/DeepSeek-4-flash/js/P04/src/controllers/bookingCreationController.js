import { Router } from 'express';
import { createBooking, listBookingsByGuest } from '../services/bookingService.js';
import { searchRooms } from '../services/roomService.js';
import { requireFields, isIsoDate } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { isoDate } from '../db/seed.js';

const router = Router();

// GET /api/hotel/booking_creation
router.get('/', requireAuth, (req, res) => {
  const bookings = listBookingsByGuest(req.user.id);
  const checkIn = req.query.check_in_date || isoDate(0);
  const checkOut = req.query.check_out_date || isoDate(2);
  const capacity = req.query.capacity ? Number(req.query.capacity) : 1;
  const available = searchRooms({ check_in_date: checkIn, check_out_date: checkOut, capacity }).results;
  res.json({ bookings, availability: { check_in_date: checkIn, check_out_date: checkOut, available_rooms: available } });
});

// POST /api/hotel/booking_creation
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, ['room_id', 'check_in_date', 'check_out_date', 'contact_name', 'contact_email']);
  const { room_id, check_in_date, check_out_date, guests, contact_name, contact_email, contact_phone, card_number, card_expiry, card_cvc, idempotency_key } = req.body;
  if (!isIsoDate(check_in_date) || !isIsoDate(check_out_date)) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Booking dates must be valid YYYY-MM-DD dates.');
  }
  if (check_out_date <= check_in_date) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Check-out date must be after check-in date.');
  }
  const outcome = createBooking({
    guest_id: req.user.id,
    room_id: Number(room_id),
    check_in_date,
    check_out_date,
    guests: Number(guests) || 1,
    contact_name,
    contact_email,
    contact_phone: contact_phone || '',
    card_number: card_number || '',
    card_expiry: card_expiry || '',
    card_cvc: card_cvc || '',
    idempotency_key: idempotency_key || null,
  });
  res.status(outcome.duplicate ? 200 : 201).json({ ...outcome, message: outcome.duplicate ? 'Existing booking returned (idempotent).' : 'Booking created.' });
});

// PATCH /api/hotel/booking_creation/:id  (update a draft booking's contact details)
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const booking = listBookingsByGuest(req.user.id).find((b) => b.id === id);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found for this account.');
  if (booking.status !== 'confirmed') throw new AppError(409, 'INVALID_STATE', 'Only confirmed bookings can be updated.');
  res.json({ ok: true, booking, message: 'Contact details are updated via booking management.' });
});

export default router;
