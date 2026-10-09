import { staffUpdateStatus, findBookingById, bookingsAll } from '../services/bookings.js';
import { validateStaffStatus } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireRole } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerStaffCheckRoutes(app) {
  app.get('/api/hotel/staff_check_in_out', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    const today = new Date().toISOString().slice(0, 10);
    const arrivals = bookingsAll().filter(b => b.check_in <= today && b.check_out >= today && b.status === 'confirmed');
    const inhouse = bookingsAll().filter(b => b.status === 'checked_in');
    const departures = bookingsAll().filter(b => b.check_out === today && b.status === 'checked_in');
    sendJson(res, 200, { arrivals, inhouse, departures });
  }));

  app.post('/api/hotel/staff_check_in_out', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    const errs = validateStaffStatus(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const internal = req.body.status === 'check_in' ? 'check_in' : 'check_out';
      const result = staffUpdateStatus(req.session.user, req.body.booking_id, internal, req.body.reason || '');
      record({ actor: req.session.user, action: `booking.${internal}`, targetType: 'booking', targetId: req.body.booking_id, detail: req.body.reason || '' });
      sendJson(res, 200, { booking: result });
    } catch (e) {
      if (e.code === 'conflict') return sendJson(res, 409, { error: e.code, message: e.message });
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/staff_check_in_out/:id', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const booking = findBookingById(id);
    if (!booking) return sendJson(res, 404, { error: 'not_found' });
    sendJson(res, 200, { booking });
  }));
}