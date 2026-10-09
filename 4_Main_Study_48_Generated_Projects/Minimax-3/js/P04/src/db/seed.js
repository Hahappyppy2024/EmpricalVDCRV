import crypto from 'node:crypto';
import { getDb, runSchema } from './connection.js';

const db = getDb();
runSchema(db);

function hashPassword(pw, salt) {
  const s = salt || crypto.randomBytes(16).toString('hex');
  const h = crypto.scryptSync(pw, s, 64).toString('hex');
  return `${s}:${h}`;
}

function passwordHash(pw) {
  return hashPassword(pw, 'salt_' + pw);
}

function todayPlus(days) {
  const d = new Date();
  d.setUTCHours(0, 0, 0, 0);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

function id(prefix) {
  return prefix + '-' + crypto.randomBytes(4).toString('hex');
}

console.log('[seed] populating deterministic data');

const tx = db.transaction(() => {
  db.exec('DELETE FROM receipts; DELETE FROM invoices; DELETE FROM notifications; DELETE FROM reviews; DELETE FROM messages; DELETE FROM booking_status_log; DELETE FROM bookings; DELETE FROM room_blocks; DELETE FROM rooms; DELETE FROM audit_events; DELETE FROM sessions; DELETE FROM users;');

  const insertUser = db.prepare('INSERT INTO users (id, email, password_hash, display_name, role) VALUES (?, ?, ?, ?, ?)');
  const guest1 = insertUser.run(1, 'alice@example.com', passwordHash('guest123'), 'Alice Guest', 'guest').lastInsertRowid;
  const guest2 = insertUser.run(2, 'bob@example.com', passwordHash('guest123'), 'Bob Guest', 'guest').lastInsertRowid;
  const staff1 = insertUser.run(3, 'staff@example.com', passwordHash('staff123'), 'Carol Staff', 'staff').lastInsertRowid;
  const admin1 = insertUser.run(4, 'admin@example.com', passwordHash('admin123'), 'Dave Admin', 'admin').lastInsertRowid;
  const mod1 = insertUser.run(5, 'mod@example.com', passwordHash('mod123'), 'Eve Moderator', 'moderator').lastInsertRowid;
  const guest3 = insertUser.run(6, 'charlie@example.com', passwordHash('guest123'), 'Charlie Guest', 'guest').lastInsertRowid;

  const insertRoom = db.prepare('INSERT INTO rooms (id, code, name, description, capacity, nightly_rate_cents, amenities, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  const r1 = insertRoom.run(1, 'STD-101', 'Standard Room 101', 'Comfortable standard room with queen bed, city view, and free Wi-Fi.', 2, 12000, 'wifi,ac,tv', '/img/room-standard.svg', 'active').lastInsertRowid;
  const r2 = insertRoom.run(2, 'DLX-201', 'Deluxe Room 201', 'Spacious deluxe room, king bed, balcony, and complimentary breakfast.', 3, 18500, 'wifi,ac,tv,balcony,breakfast', '/img/room-deluxe.svg', 'active').lastInsertRowid;
  const r3 = insertRoom.run(3, 'STE-301', 'Executive Suite 301', 'Executive suite with separate living area, premium bath, and lounge access.', 4, 32500, 'wifi,ac,tv,balcony,lounge', '/img/room-suite.svg', 'active').lastInsertRowid;
  const r4 = insertRoom.run(4, 'FM-102', 'Family Room 102', 'Family room with two queen beds, ideal for groups.', 4, 22000, 'wifi,ac,tv', '/img/room-family.svg', 'active').lastInsertRowid;
  const r5 = insertRoom.run(5, 'STD-103', 'Standard Room 103', 'Cozy standard room near the elevator.', 2, 11500, 'wifi,ac,tv', '/img/room-standard.svg', 'maintenance').lastInsertRowid;

  const insertBlock = db.prepare('INSERT INTO room_blocks (room_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)');
  insertBlock.run(r1, todayPlus(40), todayPlus(42), 'maintenance');
  insertBlock.run(r5, todayPlus(0), todayPlus(60), 'full refurbishment');

  const insertBooking = db.prepare('INSERT INTO bookings (id, code, user_id, room_id, check_in, check_out, guests, status, total_cents, payment_status, card_last4, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  const insertLog = db.prepare('INSERT INTO booking_status_log (booking_id, actor_id, from_status, to_status, reason) VALUES (?, ?, ?, ?, ?)');

  const b1 = insertBooking.run(1, id('BK'), guest1, r2, todayPlus(7), todayPlus(10), 2, 'confirmed', 55500, 'paid', '4242', 'Late check-in requested').lastInsertRowid;
  insertLog.run(b1, guest1, 'pending', 'confirmed', 'payment captured');
  insertLog.run(b1, staff1, 'confirmed', 'confirmed', 'verified payment');

  const b2 = insertBooking.run(2, id('BK'), guest1, r1, todayPlus(-20), todayPlus(-17), 2, 'checked_out', 36000, 'paid', '4242', '').lastInsertRowid;
  insertLog.run(b2, guest1, 'pending', 'confirmed', 'payment captured');
  insertLog.run(b2, staff1, 'confirmed', 'checked_in', 'guest arrived');
  insertLog.run(b2, staff1, 'checked_in', 'checked_out', 'guest departed');

  const b3 = insertBooking.run(3, id('BK'), guest2, r3, todayPlus(15), todayPlus(18), 3, 'pending', 97500, 'unpaid', '1111', '').lastInsertRowid;
  insertLog.run(b3, guest2, 'pending', 'pending', 'awaiting payment');

  const b4 = insertBooking.run(4, id('BK'), guest3, r4, todayPlus(2), todayPlus(5), 4, 'confirmed', 66000, 'paid', '0000', 'Need extra towels').lastInsertRowid;
  insertLog.run(b4, guest3, 'pending', 'confirmed', 'payment captured');

  const insertMessage = db.prepare('INSERT INTO messages (booking_id, sender_id, sender_role, body) VALUES (?, ?, ?, ?)');
  insertMessage.run(b1, guest1, 'guest', 'Could we have a late check-in around 11 PM?');
  insertMessage.run(b1, staff1, 'staff', 'Yes, front desk will be open. We will hold the room.');
  insertMessage.run(b2, guest1, 'guest', 'Thank you for the stay!');
  insertMessage.run(b2, staff1, 'staff', 'You are welcome. We hope to see you again.');

  const insertReview = db.prepare('INSERT INTO reviews (booking_id, user_id, rating, body, status) VALUES (?, ?, ?, ?, ?)');
  insertReview.run(b2, guest1, 5, 'Wonderful stay, friendly staff and clean room.', 'approved');

  const insertInvoice = db.prepare('INSERT INTO invoices (booking_id, number, total_cents, tax_cents, status) VALUES (?, ?, ?, ?, ?)');
  insertInvoice.run(b2, id('INV'), 36000, 3000, 'paid');

  const insertReceipt = db.prepare('INSERT INTO receipts (booking_id, number, amount_cents, method) VALUES (?, ?, ?, ?)');
  insertReceipt.run(b2, id('RCPT'), 36000, 'card-4242');

  const insertNotif = db.prepare('INSERT INTO notifications (user_id, kind, body) VALUES (?, ?, ?)');
  insertNotif.run(guest1, 'booking', 'Your booking ' + b2 + ' has been completed.');
  insertNotif.run(staff1, 'task', 'Check-in pending for booking ' + b1);

  const insertAudit = db.prepare('INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?, ?)');
  insertAudit.run(admin1, 'admin', 'seed.bootstrap', 'system', null, 'initial seed fixture');
  insertAudit.run(staff1, 'staff', 'booking.checked_in', 'booking', b2, 'manual check-in');
});

tx();

console.log('[seed] done.');
db.close();