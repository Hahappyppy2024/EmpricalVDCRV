import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { requireFields } from '../middleware/validation.js';
import { AppError } from '../middleware/errors.js';
import { getDb } from '../db/database.js';
import { isRoomAvailable } from '../services/roomService.js';
import { findConflictRoom } from '../services/bookingService.js';
import { isoDate } from '../db/seed.js';

const router = Router();

function logReport(userId, context, payload) {
  const db = getDb();
  const info = db.prepare(`
    INSERT INTO frontend_error_reports (user_id, context, payload) VALUES (?, ?, ?)
  `).run(userId || null, context, JSON.stringify(payload));
  return db.prepare('SELECT * FROM frontend_error_reports WHERE id = ?').get(info.lastInsertRowid);
}

// GET /api/hotel/frontend_api_integration_and_errors
router.get('/', (req, res) => {
  const db = getDb();
  const reports = db.prepare(`
    SELECT r.*, u.name AS user_name FROM frontend_error_reports r
    LEFT JOIN users u ON u.id = r.user_id
    ORDER BY r.created_at DESC, r.id DESC LIMIT 100
  `).all();
  res.json({ reports });
});

// POST /api/hotel/frontend_api_integration_and_errors
router.post('/', requireAuth, (req, res) => {
  const { action } = req.body;
  if (action === 'simulate') {
    const { scenario } = req.body;
    if (!scenario) throw new AppError(400, 'VALIDATION_ERROR', 'A scenario is required.');
    const seeded = simulateScenario(scenario);
    const record = logReport(req.user.id, `simulate:${scenario}`, seeded);
    return res.json({ scenario, simulated: seeded, record_id: record.id });
  }
  requireFields(req.body, ['context']);
  const record = logReport(req.user.id, req.body.context, req.body.payload || {});
  res.status(201).json({ report: record, message: 'Frontend error report recorded.' });
});

function simulateScenario(scenario) {
  const db = getDb();
  switch (scenario) {
    case 'unavailable': {
      // The R301 Corner Suite has a seeded maintenance block from +15 to +18.
      const room = db.prepare("SELECT id, name FROM rooms WHERE name = 'R301 Corner Suite'").get();
      const checkIn = isoDate(16);
      const checkOut = isoDate(18);
      const available = isRoomAvailable(room.id, checkIn, checkOut);
      return {
        kind: 'unavailable_dates',
        room: room.name,
        check_in_date: checkIn,
        check_out_date: checkOut,
        available,
        message: available
          ? 'The room is available for these dates.'
          : 'These dates are unavailable — the room is blocked for maintenance or already booked.',
      };
    }
    case 'conflict': {
      const checkIn = isoDate(0);
      const checkOut = isoDate(2);
      const conflict = findConflictRoom(checkIn, checkOut);
      return conflict
        ? { kind: 'date_conflict', check_in_date: checkIn, check_out_date: checkOut, conflict_room: conflict.room_name, message: 'A conflicting booking already exists for this span.' }
        : { kind: 'date_conflict', check_in_date: checkIn, check_out_date: checkOut, conflict_room: null, message: 'No conflict detected for this span.' };
    }
    case 'validation': {
      return {
        kind: 'validation_state',
        message: 'Missing required fields: check_in_date, check_out_date.',
        code: 'VALIDATION_ERROR',
        status: 400,
      };
    }
    case 'unauthorized': {
      return {
        kind: 'unauthorized',
        message: 'You must be signed in to perform this action.',
        code: 'UNAUTHORIZED',
        status: 401,
      };
    }
    case 'forbidden': {
      return {
        kind: 'forbidden',
        message: 'You do not have permission to perform this action.',
        code: 'FORBIDDEN',
        status: 403,
      };
    }
    default:
      throw new AppError(400, 'VALIDATION_ERROR', 'Unknown scenario. Use unavailable, conflict, validation, unauthorized, or forbidden.');
  }
}

// PATCH /api/hotel/frontend_api_integration_and_errors/:id
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const row = db.prepare('SELECT * FROM frontend_error_reports WHERE id = ?').get(id);
  if (!row) throw new AppError(404, 'REPORT_NOT_FOUND', 'Error report not found.');
  if (row.user_id !== req.user.id && !['admin', 'staff'].includes(req.user.role)) {
    throw new AppError(403, 'FORBIDDEN', 'You can only update your own error reports.');
  }
  const context = req.body.context !== undefined ? req.body.context : row.context;
  const payload = req.body.payload !== undefined ? JSON.stringify(req.body.payload) : row.payload;
  db.prepare('UPDATE frontend_error_reports SET context = ?, payload = ? WHERE id = ?').run(context, payload, id);
  res.json({ ok: true, message: 'Error report updated.' });
});

export default router;
