import { Router } from 'express';
import {
  listRooms, createRoom, updateRoom, createAvailabilityBlock, listAvailabilityBlocks, deleteAvailabilityBlock, getAvailabilityBlock,
} from '../services/roomService.js';
import { recordAudit } from '../services/auditService.js';
import { requireFields, isIsoDate } from '../middleware/validation.js';
import { requireRole } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { getDb } from '../db/database.js';

const router = Router();

const ROOM_TYPES = ['single', 'double', 'suite', 'deluxe', 'family'];

// GET /api/hotel/room_inventory
router.get('/', requireRole('staff', 'admin'), (req, res) => {
  res.json({ rooms: listRooms(), availability_blocks: listAvailabilityBlocks() });
});

// POST /api/hotel/room_inventory
router.post('/', requireRole('staff', 'admin'), (req, res) => {
  const { entity } = req.body;
  if (entity === 'room') {
    requireFields(req.body, ['name', 'type', 'price_per_night', 'capacity']);
    if (!ROOM_TYPES.includes(req.body.type)) throw new AppError(400, 'VALIDATION_ERROR', `Room type must be one of: ${ROOM_TYPES.join(', ')}.`);
    if (!req.body.description) throw new AppError(400, 'VALIDATION_ERROR', 'Room description is required.');
    const room = createRoom({
      name: req.body.name.trim(),
      type: req.body.type,
      description: req.body.description,
      price_per_night: Number(req.body.price_per_night),
      capacity: Number(req.body.capacity),
      amenities: Array.isArray(req.body.amenities) ? req.body.amenities : String(req.body.amenities || '').split(',').map((s) => s.trim()).filter(Boolean),
      image_url: req.body.image_url,
    });
    recordAudit({ actorId: req.user.id, action: 'create', targetType: 'room', targetId: room.id, details: { name: room.name } });
    return res.status(201).json({ room, message: 'Room created.' });
  }
  if (entity === 'block') {
    requireFields(req.body, ['room_id', 'start_date', 'end_date']);
    if (!isIsoDate(req.body.start_date) || !isIsoDate(req.body.end_date)) {
      throw new AppError(400, 'VALIDATION_ERROR', 'Block dates must be valid YYYY-MM-DD dates.');
    }
    const block = createAvailabilityBlock({
      room_id: Number(req.body.room_id),
      start_date: req.body.start_date,
      end_date: req.body.end_date,
      reason: req.body.reason || 'maintenance',
      created_by: req.user.id,
    });
    recordAudit({ actorId: req.user.id, action: 'create', targetType: 'availability_block', targetId: block.id, details: { reason: block.reason } });
    return res.status(201).json({ block, message: 'Availability block created.' });
  }
  throw new AppError(400, 'VALIDATION_ERROR', 'Entity must be room or block.');
});

// PATCH /api/hotel/room_inventory/:id
router.patch('/:id', requireRole('staff', 'admin'), (req, res) => {
  const id = Number(req.params.id);
  const { entity } = req.body;
  if (entity === 'block') {
    const block = getAvailabilityBlock(id);
    if (!block) throw new AppError(404, 'BLOCK_NOT_FOUND', 'Availability block not found.');
    if (req.body.reason !== undefined) {
      getDb().prepare('UPDATE availability_blocks SET reason = ? WHERE id = ?').run(req.body.reason, id);
    }
    recordAudit({ actorId: req.user.id, action: 'update', targetType: 'availability_block', targetId: id, details: { reason: req.body.reason } });
    return res.json({ block: getAvailabilityBlock(id), message: 'Availability block updated.' });
  }
  const room = updateRoom(id, req.body);
  recordAudit({ actorId: req.user.id, action: 'update', targetType: 'room', targetId: id, details: { name: room.name } });
  res.json({ room, message: 'Room updated.' });
});

// DELETE /api/hotel/room_inventory/block/:id  (helper to remove a block)
router.delete('/block/:id', requireRole('staff', 'admin'), (req, res) => {
  deleteAvailabilityBlock(Number(req.params.id));
  recordAudit({ actorId: req.user.id, action: 'delete', targetType: 'availability_block', targetId: Number(req.params.id) });
  res.json({ ok: true, message: 'Availability block removed.' });
});

export default router;
