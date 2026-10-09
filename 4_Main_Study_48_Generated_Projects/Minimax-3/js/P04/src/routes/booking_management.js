import { performAction, findBookingById, bookingsForUser, bookingLog } from '../services/bookings.js';
import { validateBookingAction } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerBookingManagementRoutes(app) {
  app.get('/api/hotel/booking_management', requireAuth, asyncRoute(async (req, res) => {
    const bookings = bookingsForUser(req.session.user.id);
    sendJson(res, 200, { bookings });
  }));

  app.post('/api/hotel/booking_management', requireAuth, asyncRoute(async (req, res) => {
    const body = req.body || {};
    const id = Number(body.booking_id);
    if (!id) return sendJson(res, 400, { error: 'validation_error', message: 'booking_id required' });
    const errs = validateBookingAction(body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const result = performAction(req.session.user, id, body.action, body, body.reason || '');
      record({ actor: req.session.user, action: `booking.${body.action}`, targetType: 'booking', targetId: id, detail: body.reason || '' });
      sendJson(res, 200, { booking: result });
    } catch (e) {
      if (e.code === 'conflict') return sendJson(res, 409, { error: e.code, message: e.message });
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      if (e.code === 'validation_error') return sendJson(res, 400, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/booking_management/:id', requireAuth, asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const booking = findBookingById(id);
    if (!booking) return sendJson(res, 404, { error: 'not_found' });
    if (booking.user_id !== req.session.user.id && req.session.user.role === 'guest') return sendJson(res, 403, { error: 'forbidden' });
    sendJson(res, 200, { booking, log: bookingLog(id) });
  }));
}