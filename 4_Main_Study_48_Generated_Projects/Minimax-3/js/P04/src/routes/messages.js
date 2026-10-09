import { listMessages, postMessage } from '../services/messages.js';
import { validateMessage } from '../services/validate.js';
import { record } from '../services/audit.js';
import { notify } from '../services/notify.js';
import { findBookingById } from '../services/bookings.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerGuestMessagesRoutes(app) {
  app.get('/api/hotel/guest_messages', requireAuth, asyncRoute(async (req, res) => {
    const bookingId = Number(req.query.booking_id);
    if (!bookingId) return sendJson(res, 400, { error: 'validation_error', message: 'booking_id required' });
    try {
      const messages = listMessages(req.session.user, bookingId);
      sendJson(res, 200, { messages });
    } catch (e) {
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.post('/api/hotel/guest_messages', requireAuth, asyncRoute(async (req, res) => {
    const errs = validateMessage(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const message = postMessage(req.session.user, req.body.booking_id, req.body.body);
      const booking = findBookingById(req.body.booking_id);
      if (req.session.user.role === 'guest') notify(booking.user_id, 'message', `New message from staff`);
      else notify(booking.user_id, 'message', `Staff replied: ${message.body.slice(0, 60)}`);
      record({ actor: req.session.user, action: 'message.send', targetType: 'booking', targetId: req.body.booking_id });
      sendJson(res, 201, { message });
    } catch (e) {
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/guest_messages/:id', requireAuth, asyncRoute(async (req, res) => {
    sendJson(res, 200, { ok: true });
  }));
}