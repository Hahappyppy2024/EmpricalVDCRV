import { listRooms, listBlocks, createRoom, updateRoom, addBlock } from '../services/rooms.js';
import { validateRoomInventoryUpsert, validateRoomBlock } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireRole } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerRoomInventoryRoutes(app) {
  app.get('/api/hotel/room_inventory', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    sendJson(res, 200, { rooms: listRooms(), blocks: listBlocks() });
  }));

  app.post('/api/hotel/room_inventory', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    const body = req.body || {};
    if (body.action === 'create_room') {
      const errs = validateRoomInventoryUpsert(body);
      if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
      const room = createRoom(body);
      record({ actor: req.session.user, action: 'inventory.create_room', targetType: 'room', targetId: room.id, detail: room.code });
      return sendJson(res, 201, { room });
    }
    if (body.action === 'add_block') {
      const errs = validateRoomBlock(body);
      if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
      const block = addBlock(body);
      record({ actor: req.session.user, action: 'inventory.add_block', targetType: 'room_block', targetId: block.id, detail: `${body.start_date}..${body.end_date}` });
      return sendJson(res, 201, { block });
    }
    sendJson(res, 400, { error: 'validation_error', message: 'Unknown action' });
  }));

  app.patch('/api/hotel/room_inventory/:id', requireRole('staff', 'admin'), asyncRoute(async (req, res) => {
    const id = Number(req.params.id);
    const room = updateRoom(id, req.body || {});
    record({ actor: req.session.user, action: 'inventory.update_room', targetType: 'room', targetId: id });
    sendJson(res, 200, { room });
  }));
}