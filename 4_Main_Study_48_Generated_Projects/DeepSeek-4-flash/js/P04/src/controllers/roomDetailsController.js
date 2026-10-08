import { Router } from 'express';
import { getRoom, listRooms, searchRooms } from '../services/roomService.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { requireFields, isIsoDate } from '../middleware/validation.js';
import { getDb } from '../db/database.js';
import { isoDate } from '../db/seed.js';

const router = Router();

function recordView(userId, roomId) {
  const db = getDb();
  db.prepare('INSERT INTO room_details_views (user_id, room_id) VALUES (?, ?)').run(userId || null, roomId);
}

// GET /api/hotel/room_details?room_id=1
// GET /api/hotel/room_details/:id
router.get(['/', '/:id'], (req, res) => {
  const id = Number(req.params.id || req.query.room_id || req.query.id);
  if (!id) {
    const rooms = listRooms().filter((r) => r.status === 'active');
    return res.json({ count: rooms.length, rooms });
  }
  const room = getRoom(id);
  if (!room) throw new AppError(404, 'ROOM_NOT_FOUND', 'Room not found.');
  recordView(req.isAuthenticated ? req.user.id : null, id);

  const checkIn = req.query.check_in_date || isoDate(0);
  const checkOut = req.query.check_out_date || isoDate(1);
  const availability = checkIn && checkOut && checkOut > checkIn
    ? { check_in_date: checkIn, check_out_date: checkOut, available: searchRooms({ check_in_date: checkIn, check_out_date: checkOut }).results.some((r) => r.id === room.id) }
    : null;

  const db = getDb();
  const viewCount = db.prepare('SELECT COUNT(*) AS n FROM room_details_views WHERE room_id = ?').get(id).n;

  res.json({ room, availability, view_count: viewCount });
});

// POST /api/hotel/room_details
router.post('/', (req, res) => {
  requireFields(req.body, ['room_id']);
  const room = getRoom(Number(req.body.room_id));
  if (!room) throw new AppError(404, 'ROOM_NOT_FOUND', 'Room not found.');
  recordView(req.isAuthenticated ? req.user.id : null, room.id);
  res.status(201).json({ room, message: 'Room details viewed.' });
});

// PATCH /api/hotel/room_details/:id  (annotate a view record)
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const view = db.prepare('SELECT * FROM room_details_views WHERE id = ?').get(id);
  if (!view) throw new AppError(404, 'VIEW_NOT_FOUND', 'Room details view record not found.');
  if (view.user_id !== req.user.id) throw new AppError(403, 'FORBIDDEN', 'You can only annotate your own view records.');
  const note = req.body.note;
  if (note === undefined) throw new AppError(400, 'VALIDATION_ERROR', 'A note is required.');
  db.prepare('UPDATE room_details_views SET note = ? WHERE id = ?').run(note, id);
  res.json({ ok: true, note, message: 'View record annotated.' });
});

export default router;
