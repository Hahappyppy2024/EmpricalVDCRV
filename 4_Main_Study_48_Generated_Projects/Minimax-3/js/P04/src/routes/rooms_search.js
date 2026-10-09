import { searchRooms, findRoomById } from '../services/rooms.js';
import { validateRoomSearch } from '../services/validate.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerRoomRoutes(app) {
  app.get('/api/hotel/room_search', asyncRoute(async (req, res) => {
    const params = Object.assign({}, req.query || {});
    const errs = validateRoomSearch(params);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    const results = searchRooms({
      check_in: params.check_in,
      check_out: params.check_out,
      guests: params.guests || 1,
      max_price: params.max_price,
      amenities: params.amenities,
    });
    sendJson(res, 200, { results, count: results.length });
  }));

  app.post('/api/hotel/room_search', asyncRoute(async (req, res) => {
    const body = req.body || {};
    const errs = validateRoomSearch(body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    const results = searchRooms({
      check_in: body.check_in,
      check_out: body.check_out,
      guests: body.guests || 1,
      max_price: body.max_price,
      amenities: body.amenities,
    });
    sendJson(res, 200, { results, count: results.length });
  }));

  app.patch('/api/hotel/room_search/:id', asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const room = findRoomById(id);
    if (!room) return sendJson(res, 404, { error: 'not_found' });
    sendJson(res, 200, { room });
  }));
}