import { getDb } from '../db/connection.js';
import { badRequest, conflict, notFound, forbidden, unauthorized } from './errors.js';

export function findRoomById(id) {
  return getDb().prepare('SELECT * FROM rooms WHERE id = ?').get(id);
}

export function listRooms() {
  return getDb().prepare('SELECT * FROM rooms ORDER BY id').all();
}

export function searchRooms({ check_in, check_out, guests = 1, max_price, amenities }) {
  const db = getDb();
  let sql = `SELECT DISTINCT r.* FROM rooms r
             WHERE r.status = 'active' AND r.capacity >= ?`;
  const params = [Number(guests) || 1];
  if (max_price !== undefined && max_price !== null && max_price !== '') {
    sql += ' AND r.nightly_rate_cents <= ?';
    params.push(Number(max_price));
  }
  if (amenities) {
    const list = (Array.isArray(amenities) ? amenities : String(amenities).split(','))
      .map(s => s.trim()).filter(Boolean);
    for (const a of list) {
      sql += ' AND r.amenities LIKE ?';
      params.push('%' + a + '%');
    }
  }
  sql += ` AND NOT EXISTS (
    SELECT 1 FROM bookings b
    WHERE b.room_id = r.id AND b.status IN ('pending','confirmed','checked_in')
      AND NOT (b.check_out <= ? OR b.check_in >= ?)
  )`;
  sql += ` AND NOT EXISTS (
    SELECT 1 FROM room_blocks rb
    WHERE rb.room_id = r.id AND NOT (rb.end_date <= ? OR rb.start_date >= ?)
  )`;
  params.push(check_in, check_out, check_in, check_out);
  sql += ' ORDER BY r.nightly_rate_cents ASC';
  return db.prepare(sql).all(...params);
}

export function roomAvailability(id, check_in, check_out) {
  const db = getDb();
  const conflicts = db.prepare(`SELECT id, check_in, check_out FROM bookings
                                WHERE room_id = ? AND status IN ('pending','confirmed','checked_in')
                                  AND NOT (check_out <= ? OR check_in >= ?)`).all(id, check_in, check_out);
  const blocks = db.prepare(`SELECT id, start_date, end_date FROM room_blocks
                             WHERE room_id = ? AND NOT (end_date <= ? OR start_date >= ?)`).all(id, check_in, check_out);
  return { available: conflicts.length === 0 && blocks.length === 0, conflicts, blocks };
}

export function createRoom(input) {
  const db = getDb();
  const exists = db.prepare('SELECT id FROM rooms WHERE code = ?').get(input.code);
  if (exists) throw conflict('Room code already exists');
  const info = db.prepare(`INSERT INTO rooms (code, name, description, capacity, nightly_rate_cents, amenities, image_url, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)`).run(
    input.code, input.name, input.description || '', Number(input.capacity), Number(input.nightly_rate_cents),
    input.amenities || '', input.image_url || '', input.status || 'active'
  );
  return findRoomById(info.lastInsertRowid);
}

export function updateRoom(id, patch) {
  const db = getDb();
  const room = findRoomById(id);
  if (!room) throw notFound('Room not found');
  const fields = ['name','description','capacity','nightly_rate_cents','amenities','image_url','status'];
  const sets = [];
  const params = [];
  for (const f of fields) {
    if (patch[f] !== undefined) {
      sets.push(`${f} = ?`);
      params.push(patch[f]);
    }
  }
  if (!sets.length) return room;
  params.push(id);
  db.prepare(`UPDATE rooms SET ${sets.join(', ')} WHERE id = ?`).run(...params);
  return findRoomById(id);
}

export function addBlock(input) {
  const db = getDb();
  const room = findRoomById(input.room_id);
  if (!room) throw notFound('Room not found');
  const info = db.prepare(`INSERT INTO room_blocks (room_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)`)
    .run(input.room_id, input.start_date, input.end_date, input.reason);
  return db.prepare('SELECT * FROM room_blocks WHERE id = ?').get(info.lastInsertRowid);
}

export function listBlocks() {
  return getDb().prepare('SELECT rb.*, r.code AS room_code FROM room_blocks rb JOIN rooms r ON r.id = rb.room_id ORDER BY rb.start_date DESC').all();
}