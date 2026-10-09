import { getOrCreateInvoice, getOrCreateReceipt, invoiceHtml } from '../services/invoice.js';
import { findBookingById } from '../services/bookings.js';
import { findRoomById } from '../services/rooms.js';
import { getDb } from '../db/connection.js';
import { validateInvoiceRequest, validateReceiptRequest } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';
import { env } from '../config.js';

export function registerInvoiceRoutes(app) {
  app.get('/api/hotel/invoice_and_receipt', requireAuth, asyncRoute(async (req, res) => {
    const bookingId = Number(req.query.booking_id);
    const errs = validateInvoiceRequest({ booking_id: bookingId });
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const invoice = getOrCreateInvoice(req.session.user, bookingId);
      const booking = findBookingById(bookingId);
      const room = findRoomById(booking.room_id);
      const cfg = env();
      const html = invoiceHtml(invoice, booking, room, req.session.user, cfg.HOTEL_NAME);
      if (req.query.format === 'json') return sendJson(res, 200, { invoice, booking, room });
      res.statusCode = 200;
      res.setHeader('content-type', 'text/html; charset=utf-8');
      return res.end(html);
    } catch (e) {
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.post('/api/hotel/invoice_and_receipt', requireAuth, asyncRoute(async (req, res) => {
    const body = req.body || {};
    const errs = validateReceiptRequest(body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    try {
      const receipt = getOrCreateReceipt(req.session.user, body.booking_id);
      record({ actor: req.session.user, action: 'invoice.receipt_generate', targetType: 'booking', targetId: body.booking_id });
      sendJson(res, 201, { receipt });
    } catch (e) {
      if (e.code === 'not_found') return sendJson(res, 404, { error: e.code, message: e.message });
      if (e.code === 'forbidden') return sendJson(res, 403, { error: e.code, message: e.message });
      if (e.code === 'validation_error') return sendJson(res, 400, { error: e.code, message: e.message });
      throw e;
    }
  }));

  app.patch('/api/hotel/invoice_and_receipt/:id', requireAuth, asyncRoute(async (req, res) => {
    sendJson(res, 200, { ok: true });
  }));
}