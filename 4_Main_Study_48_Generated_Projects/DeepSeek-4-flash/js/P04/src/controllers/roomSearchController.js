import { Router } from 'express';
import { searchRooms } from '../services/roomService.js';
import { requireFields, isIsoDate } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { getDb } from '../db/database.js';
import { nowSql } from '../services/sessionService.js';

const router = Router();

function toFilters(body) {
  const filters = {};
  for (const key of ['check_in_date', 'check_out_date', 'capacity', 'min_price', 'max_price', 'type', 'q']) {
    if (body[key] !== undefined && body[key] !== '') filters[key] = body[key];
  }
  if (filters.check_in_date && !isIsoDate(filters.check_in_date)) throw new AppError(400, 'VALIDATION_ERROR', 'check_in_date must be a valid YYYY-MM-DD date.');
  if (filters.check_out_date && !isIsoDate(filters.check_out_date)) throw new AppError(400, 'VALIDATION_ERROR', 'check_out_date must be a valid YYYY-MM-DD date.');
  if (filters.check_in_date && filters.check_out_date && filters.check_out_date <= filters.check_in_date) {
    throw new AppError(400, 'VALIDATION_ERROR', 'check_out_date must be after check_in_date.');
  }
  return filters;
}

function recordSearch(userId, filters, count) {
  const db = getDb();
  const info = db.prepare(`
    INSERT INTO room_search_records (user_id, query, results_count) VALUES (?, ?, ?)
  `).run(userId || null, JSON.stringify(filters), count);
  return info.lastInsertRowid;
}

// GET /api/hotel/room_search
router.get('/', (req, res) => {
  const filters = toFilters(req.query);
  const { results, count } = searchRooms(filters);
  const recordId = recordSearch(req.isAuthenticated ? req.user.id : null, filters, count);
  res.json({ record_id: recordId, filters, count, results });
});

// POST /api/hotel/room_search
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, []);
  const filters = toFilters(req.body);
  const { results, count } = searchRooms(filters);
  const recordId = recordSearch(req.user.id, filters, count);
  res.status(201).json({ record_id: recordId, filters, count, results });
});

// PATCH /api/hotel/room_search/:id
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const row = db.prepare('SELECT * FROM room_search_records WHERE id = ?').get(id);
  if (!row) throw new AppError(404, 'SEARCH_NOT_FOUND', 'Search record not found.');
  if (row.user_id !== req.user.id) throw new AppError(403, 'FORBIDDEN', 'You can only update your own search records.');
  const query = req.body.query !== undefined ? JSON.stringify(req.body.query) : row.query;
  db.prepare('UPDATE room_search_records SET query = ? WHERE id = ?').run(query, id);
  const updated = db.prepare('SELECT * FROM room_search_records WHERE id = ?').get(id);
  res.json({ record: updated, message: 'Search record updated.' });
});

export default router;
