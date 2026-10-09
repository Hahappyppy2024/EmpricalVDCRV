import { createBooking, bookingsForUser, findBookingById, bookingLog } from '../services/bookings.js';
import { validateBooking, validateBookingAction } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerBookingCreationRoutes(app) {
  app.get('/api/hotel/booking_creation', requireAuth, asyncRoute(async (req, res) => {
    const bookings = bookingsForUser(req.session.user.id);
    sendJson(res, 200, { bookings });
  }));

  app.post('/api/hotel/booking_creation', requireAuth, asyncRoute(async (req, res) => {
    const errs = validateBooking(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const booking = createBooking({ user: req.session.user, ...req.body });
      record({ actor: req.session.user, action: 'booking.create', targetType: 'booking', targetId: booking.id, detail: booking.code });
      sendJson(res, 201, { booking });
    } catch (e) {
      if (e.code === 'conflict') return sendJson(res, 409, { error: e.code, message: e.message });
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'validation_error') return sendJson(res, 400, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/booking_creation/:id', requireAuth, asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const booking = findBookingById(id);
    if (!booking) return sendJson(res, 404, { error: 'not_found' });
    if (booking.user_id !== req.session.user.id) return sendJson(res, 403, { error: 'forbidden' });
    sendJson(res, 200, { booking, log: bookingLog(id) });
  }));
}