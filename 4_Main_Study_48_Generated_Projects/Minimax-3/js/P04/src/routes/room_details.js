import { findRoomById } from '../services/rooms.js';
import { findBookingById, bookingsAll } from '../services/bookings.js';
import { getDb } from '../db/connection.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerRoomDetailsRoutes(app) {
  app.get('/api/hotel/room_details', asyncRoute(async (req, res) => {
    const id = Number(req.query.id);
    if (!id) return sendJson(res, 400, { error: 'validation_error', message: 'id required' });
    const room = findRoomById(id);
    if (!room) return sendJson(res, 404, { error: 'not_found', message: 'Room not found' });
    const futureBookings = getDb().prepare(`SELECT id, code, check_in, check_out, status FROM bookings WHERE room_id = ? AND check_out >= date('now') AND status IN ('confirmed','checked_in') ORDER BY check_in ASC`).all(id);
    const reviews = getDb().prepare(`SELECT r.rating, r.body, r.created_at, u.display_name AS author FROM reviews r JOIN bookings b ON b.id = r.booking_id JOIN users u ON u.id = r.user_id WHERE b.room_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 5`).all(id);
    sendJson(res, 200, { room, future_bookings: futureBookings, reviews });
  }));

  app.post('/api/hotel/room_details', asyncRoute(async (req, res) => {
    const body = req.body || {};
    const id = Number(body.id);
    if (!id) return sendJson(res, 400, { error: 'validation_error', message: 'id required' });
    const room = findRoomById(id);
    if (!room) return sendJson(res, 404, { error: 'not_found', message: 'Room not found' });
    const futureBookings = getDb().prepare(`SELECT id, code, check_in, check_out, status FROM bookings WHERE room_id = ? AND check_out >= date('now') AND status IN ('confirmed','checked_in') ORDER BY check_in ASC`).all(id);
    sendJson(res, 200, { room, future_bookings: futureBookings });
  }));

  app.patch('/api/hotel/room_details/:id', asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const room = findRoomById(id);
    if (!room) return sendJson(res, 404, { error: 'not_found' });
    sendJson(res, 200, { room });
  }));
}