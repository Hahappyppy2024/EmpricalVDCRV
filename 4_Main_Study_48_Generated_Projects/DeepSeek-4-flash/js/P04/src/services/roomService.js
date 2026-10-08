import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { isoDate } from '../db/seed.js';

function publicRoom(row) {
  if (!row) return null;
  let amenities = [];
  try { amenities = JSON.parse(row.amenities || '[]'); } catch { amenities = []; }
  return {
    id: row.id,
    name: row.name,
    type: row.type,
    description: row.description,
    price_per_night: row.price_per_night,
    capacity: row.capacity,
    amenities,
    image_url: row.image_url,
    status: row.status,
    created_at: row.created_at,
  };
}

function overlapExists(roomId, checkIn, checkOut, excludeBookingId = null) {
  const db = getDb();
  const bookings = db.prepare(`
    SELECT COUNT(*) AS n FROM bookings
    WHERE room_id = ? AND status != 'cancelled'
      AND check_in_date < ? AND check_out_date > ?
      AND (? IS NULL OR id != ?)
  `).get(roomId, checkOut, checkIn, excludeBookingId ?? null, excludeBookingId ?? null);
  const blocks = db.prepare(`
    SELECT COUNT(*) AS n FROM availability_blocks
    WHERE room_id = ? AND start_date < ? AND end_date > ?
  `).get(roomId, checkOut, checkIn);
  return bookings.n > 0 || blocks.n > 0;
}

export function isRoomAvailable(roomId, checkIn, checkOut, excludeBookingId = null) {
  if (!checkIn || !checkOut) return false;
  if (checkOut <= checkIn) return false;
  return !overlapExists(roomId, checkIn, checkOut, excludeBookingId);
}

export function searchRooms(filters = {}) {
  const db = getDb();
  const { check_in_date, check_out_date, capacity, min_price, max_price, type, q } = filters;

  const roomRows = db.prepare(`
    SELECT * FROM rooms WHERE status = 'active' ORDER BY price_per_night ASC, name ASC
  `).all();

  const visible = roomRows.map(publicRoom).filter((room) => {
    if (capacity && Number(capacity) > 0 && room.capacity < Number(capacity)) return false;
    if (min_price && room.price_per_night < Number(min_price)) return false;
    if (max_price && room.price_per_night > Number(max_price)) return false;
    if (type && room.type !== type) return false;
    if (q && !(`${room.name} ${room.type} ${room.description}`.toLowerCase().includes(q.toLowerCase()))) return false;
    if (check_in_date && check_out_date && !isRoomAvailable(room.id, check_in_date, check_out_date)) return false;
    return true;
  });

  return { results: visible, count: visible.length };
}

export function getRoom(id) {
  const db = getDb();
  const row = db.prepare('SELECT * FROM rooms WHERE id = ?').get(id);
  return publicRoom(row);
}

export function listRooms() {
  const db = getDb();
  return db.prepare('SELECT * FROM rooms ORDER BY name ASC').all().map(publicRoom);
}

export function createRoom({ name, type, description, price_per_night, capacity, amenities, image_url }) {
  const db = getDb();
  const existing = db.prepare('SELECT id FROM rooms WHERE name = ?').get(name);
  if (existing) throw new AppError(409, 'ROOM_EXISTS', 'A room with that name already exists.');
  const info = db.prepare(`
    INSERT INTO rooms (name, type, description, price_per_night, capacity, amenities, image_url)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `).run(name, type, description, price_per_night, capacity, JSON.stringify(amenities || []), image_url || null);
  return getRoom(info.lastInsertRowid);
}

export function updateRoom(id, patch) {
  const db = getDb();
  const current = db.prepare('SELECT * FROM rooms WHERE id = ?').get(id);
  if (!current) throw new AppError(404, 'ROOM_NOT_FOUND', 'Room not found.');
  const allowed = ['name', 'type', 'description', 'price_per_night', 'capacity', 'image_url', 'status'];
  const updates = [];
  const values = [];
  for (const key of allowed) {
    if (patch[key] !== undefined) {
      if (key === 'amenities') {
        updates.push('amenities = ?');
        values.push(JSON.stringify(patch[key]));
      } else if (key === 'name') {
        const dup = db.prepare('SELECT id FROM rooms WHERE name = ? AND id != ?').get(patch.name, id);
        if (dup) throw new AppError(409, 'ROOM_EXISTS', 'A room with that name already exists.');
        updates.push('name = ?');
        values.push(patch.name);
      } else if (key === 'status') {
        if (!['active', 'maintenance', 'retired'].includes(patch.status)) {
          throw new AppError(400, 'VALIDATION_ERROR', 'Room status must be active, maintenance, or retired.');
        }
        updates.push('status = ?');
        values.push(patch.status);
      } else {
        updates.push(`${key} = ?`);
        values.push(patch[key]);
      }
    }
  }
  if (updates.length === 0) return getRoom(id);
  updates.push('updated_at = ?');
  values.push(isoDate(0).replace('T', ' ').slice(0, 10) + ' ' + new Date().toISOString().slice(11, 19));
  values.push(id);
  db.prepare(`UPDATE rooms SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  return getRoom(id);
}

export function createAvailabilityBlock({ room_id, start_date, end_date, reason, created_by }) {
  const db = getDb();
  const room = db.prepare('SELECT id FROM rooms WHERE id = ?').get(room_id);
  if (!room) throw new AppError(404, 'ROOM_NOT_FOUND', 'Room not found.');
  if (end_date <= start_date) throw new AppError(400, 'VALIDATION_ERROR', 'Block end date must be after start date.');
  const info = db.prepare(`
    INSERT INTO availability_blocks (room_id, start_date, end_date, reason, created_by)
    VALUES (?, ?, ?, ?, ?)
  `).run(room_id, start_date, end_date, reason || 'maintenance', created_by || null);
  return getAvailabilityBlock(info.lastInsertRowid);
}

export function getAvailabilityBlock(id) {
  const db = getDb();
  return db.prepare(`
    SELECT b.*, r.name AS room_name FROM availability_blocks b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.id = ?
  `).get(id);
}

export function listAvailabilityBlocks() {
  const db = getDb();
  return db.prepare(`
    SELECT b.*, r.name AS room_name FROM availability_blocks b
    JOIN rooms r ON r.id = b.room_id
    ORDER BY b.start_date ASC
  `).all();
}

export function deleteAvailabilityBlock(id) {
  const db = getDb();
  const info = db.prepare('DELETE FROM availability_blocks WHERE id = ?').run(id);
  if (info.changes === 0) throw new AppError(404, 'BLOCK_NOT_FOUND', 'Availability block not found.');
}
