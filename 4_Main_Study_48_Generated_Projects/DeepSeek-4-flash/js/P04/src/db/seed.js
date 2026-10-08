import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import { getDb } from './database.js';

export function isoDate(daysOffset = 0, base = new Date()) {
  const d = new Date(base);
  d.setUTCDate(d.getUTCDate() + daysOffset);
  return d.toISOString().slice(0, 10);
}

export function uid(prefix, length = 10) {
  return `${prefix}_${crypto.randomBytes(length).toString('hex').slice(0, length)}`.toUpperCase();
}

export function hashPassword(password) {
  return bcrypt.hashSync(password, 10);
}

const AMENITIES = {
  single: ['Wi-Fi', 'Ensuite bathroom', 'Desk', 'Flat-screen TV'],
  double: ['Wi-Fi', 'Ensuite bathroom', 'Desk', 'Flat-screen TV', 'Minibar'],
  suite: ['Wi-Fi', 'Ensuite bathroom', 'Living area', 'Flat-screen TV', 'Minibar', 'Jacuzzi'],
  deluxe: ['Wi-Fi', 'Ensuite bathroom', 'Balcony', 'Flat-screen TV', 'Minibar', 'Room service'],
  family: ['Wi-Fi', 'Ensuite bathroom', 'Two bedrooms', 'Flat-screen TV', 'Kitchenette', 'Crib available'],
};

const SEED_ROOMS = [
  { name: 'R101 Cozy Single', type: 'single', description: 'Compact single room on the first floor with garden view.', price_per_night: 95, capacity: 1, color: '#5b8def' },
  { name: 'R102 Garden Single', type: 'single', description: 'Single room overlooking the garden with a shared balcony.', price_per_night: 105, capacity: 1, color: '#5b8def' },
  { name: 'R201 Classic Double', type: 'double', description: 'Spacious double room with a queen bed and city view.', price_per_night: 145, capacity: 2, color: '#f2a65a' },
  { name: 'R202 Deluxe Double', type: 'double', description: 'Deluxe double with king bed, minibar, and lounge chair.', price_per_night: 175, capacity: 2, color: '#f2a65a' },
  { name: 'R301 Corner Suite', type: 'suite', description: 'Corner suite with separate living area and jacuzzi tub.', price_per_night: 260, capacity: 3, color: '#9b6bff' },
  { name: 'R302 Executive Suite', type: 'suite', description: 'Executive suite on the top floor with panoramic views.', price_per_night: 310, capacity: 3, color: '#9b6bff' },
  { name: 'R401 Skyline Deluxe', type: 'deluxe', description: 'Deluxe room with a private balcony and breakfast included.', price_per_night: 220, capacity: 2, color: '#e85d75' },
  { name: 'R402 Harbor Deluxe', type: 'deluxe', description: 'Deluxe room with harbor view and priority room service.', price_per_night: 240, capacity: 2, color: '#e85d75' },
  { name: 'R501 Family Retreat', type: 'family', description: 'Two-bedroom family suite with kitchenette and play corner.', price_per_night: 285, capacity: 4, color: '#3db07f' },
  { name: 'R502 Family Terrace', type: 'family', description: 'Family suite with a private terrace and crib available.', price_per_night: 295, capacity: 4, color: '#3db07f' },
];

export function seedDatabase() {
  const db = getDb();

  const insertUser = db.prepare(`
    INSERT INTO users (email, password_hash, name, role, phone)
    VALUES (@email, @password_hash, @name, @role, @phone)
  `);
  const insertSession = db.prepare(`
    INSERT INTO sessions (token, user_id, created_at, expires_at)
    VALUES (@token, @user_id, @created_at, @expires_at)
  `);
  const insertRoom = db.prepare(`
    INSERT INTO rooms (name, type, description, price_per_night, capacity, amenities, image_url, status)
    VALUES (@name, @type, @description, @price_per_night, @capacity, @amenities, @image_url, @status)
  `);
  const insertBlock = db.prepare(`
    INSERT INTO availability_blocks (room_id, start_date, end_date, reason, created_by)
    VALUES (?, ?, ?, ?, ?)
  `);
  const insertBooking = db.prepare(`
    INSERT INTO bookings (code, guest_id, room_id, check_in_date, check_out_date, guests, contact_name, contact_email, contact_phone, total_price, status, created_at, updated_at)
    VALUES (@code, @guest_id, @room_id, @check_in_date, @check_out_date, @guests, @contact_name, @contact_email, @contact_phone, @total_price, @status, @created_at, @updated_at)
  `);
  const insertPayment = db.prepare(`
    INSERT INTO payments (booking_id, amount, method, card_masked, status, transaction_code)
    VALUES (?, ?, 'card', ?, 'paid', ?)
  `);
  const insertInvoice = db.prepare(`
    INSERT INTO invoices (booking_id, number, total, status, issued_at)
    VALUES (?, ?, ?, 'issued', ?)
  `);
  const insertReview = db.prepare(`
    INSERT INTO reviews (booking_id, user_id, rating, text, status, moderated_by, moderated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `);
  const insertMessage = db.prepare(`
    INSERT INTO messages (booking_id, user_id, body, sender_role)
    VALUES (?, ?, ?, ?)
  `);
  const insertNotification = db.prepare(`
    INSERT INTO outbound_notifications (recipient, channel, subject, body)
    VALUES (?, 'email', ?, ?)
  `);
  const insertAccessLog = db.prepare(`
    INSERT INTO account_access_log (user_id, action, details)
    VALUES (?, ?, ?)
  `);
  const insertAudit = db.prepare(`
    INSERT INTO audit_events (actor_id, action, target_type, target_id, details)
    VALUES (?, ?, ?, ?, ?)
  `);

  const seedTx = db.transaction(() => {
    db.exec('DELETE FROM outbound_notifications');
    db.exec('DELETE FROM audit_events');
    db.exec('DELETE FROM account_access_log');
    db.exec('DELETE FROM room_search_records');
    db.exec('DELETE FROM room_details_views');
    db.exec('DELETE FROM frontend_error_reports');
    db.exec('DELETE FROM report_exports');
    db.exec('DELETE FROM check_in_out_events');
    db.exec('DELETE FROM invoices');
    db.exec('DELETE FROM reviews');
    db.exec('DELETE FROM messages');
    db.exec('DELETE FROM payments');
    db.exec('DELETE FROM bookings');
    db.exec('DELETE FROM availability_blocks');
    db.exec('DELETE FROM rooms');
    db.exec('DELETE FROM password_resets');
    db.exec('DELETE FROM sessions');
    db.exec('DELETE FROM users');

    // ---- Users ----
    const adminId = insertUser.run({
      email: 'admin@hotel.test', password_hash: hashPassword('admin123'), name: 'System Admin', role: 'admin', phone: '+1 555 0100',
    }).lastInsertRowid;
    const staffId = insertUser.run({
      email: 'staff@hotel.test', password_hash: hashPassword('staff123'), name: 'Front Desk Staff', role: 'staff', phone: '+1 555 0101',
    }).lastInsertRowid;
    const moderatorId = insertUser.run({
      email: 'moderator@hotel.test', password_hash: hashPassword('mod123'), name: 'Review Moderator', role: 'moderator', phone: '+1 555 0102',
    }).lastInsertRowid;
    const guestId = insertUser.run({
      email: 'guest@hotel.test', password_hash: hashPassword('guest123'), name: 'Grace Guest', role: 'guest', phone: '+1 555 0201',
    }).lastInsertRowid;
    const aliceId = insertUser.run({
      email: 'alice@hotel.test', password_hash: hashPassword('alice123'), name: 'Alice Adams', role: 'guest', phone: '+1 555 0202',
    }).lastInsertRowid;
    const bobId = insertUser.run({
      email: 'bob@hotel.test', password_hash: hashPassword('bob123'), name: 'Bob Brown', role: 'guest', phone: '+1 555 0203',
    }).lastInsertRowid;

    // ---- Active seed sessions ----
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
    const expires = new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
    const seedSessions = [
      ['seed_admin', adminId], ['seed_staff', staffId], ['seed_mod', moderatorId],
      ['seed_guest', guestId], ['seed_alice', aliceId], ['seed_bob', bobId],
    ];
    for (const [token, userId] of seedSessions) {
      insertSession.run({ token: `seed_${token}_${Date.now().toString(36)}`, user_id: userId, created_at: now, expires_at: expires });
    }

    // ---- Rooms ----
    const roomIds = {};
    for (const r of SEED_ROOMS) {
      const info = insertRoom.run({
        name: r.name,
        type: r.type,
        description: r.description,
        price_per_night: r.price_per_night,
        capacity: r.capacity,
        amenities: JSON.stringify(AMENITIES[r.type]),
        image_url: `/img/rooms/${r.type}.svg`,
        status: 'active',
      });
      roomIds[r.name] = info.lastInsertRowid;
    }

    // ---- Availability blocks (deterministic, relative to today) ----
    const blockSuite = roomIds['R301 Corner Suite'];
    const blockDeluxe = roomIds['R401 Skyline Deluxe'];
    insertBlock.run(blockSuite, isoDate(15), isoDate(18), 'Floor refurbishment', staffId);
    insertBlock.run(blockDeluxe, isoDate(22), isoDate(25), 'HVAC maintenance', staffId);

    // ---- Bookings ----
    const bookingIds = {};
    function mkBooking(roomName, guestId, checkInOffset, checkOutOffset, guests, contact, status, priceKey = 'base') {
      const room = SEED_ROOMS.find((r) => r.name === roomName);
      const nights = checkOutOffset - checkInOffset;
      const total = room.price_per_night * nights;
      const code = uid('BK', 8);
      const created = isoDate(-30);
      const info = insertBooking.run({
        code,
        guest_id: guestId,
        room_id: roomIds[roomName],
        check_in_date: isoDate(checkInOffset),
        check_out_date: isoDate(checkOutOffset),
        guests,
        contact_name: contact.name,
        contact_email: contact.email,
        contact_phone: contact.phone,
        total_price: total,
        status,
        created_at: created,
        updated_at: created,
      });
      bookingIds[`${guestId}_${roomName}`] = info.lastInsertRowid;
      return { id: info.lastInsertRowid, roomName, guestId, total };
    }

    const guestContact = { name: 'Grace Guest', email: 'guest@hotel.test', phone: '+1 555 0201' };
    const aliceContact = { name: 'Alice Adams', email: 'alice@hotel.test', phone: '+1 555 0202' };
    const bobContact = { name: 'Bob Brown', email: 'bob@hotel.test', phone: '+1 555 0203' };

    const guestFuture = mkBooking('R101 Cozy Single', guestId, 10, 13, 1, guestContact, 'confirmed');
    const guestCheckedIn = mkBooking('R201 Classic Double', guestId, -1, 4, 2, guestContact, 'checked_in');
    const guestCompleted = mkBooking('R202 Deluxe Double', guestId, -20, -17, 2, guestContact, 'checked_out');
    const aliceBooking = mkBooking('R301 Corner Suite', aliceId, 7, 10, 2, aliceContact, 'confirmed');
    const bobCompleted = mkBooking('R501 Family Retreat', bobId, -12, -9, 4, bobContact, 'checked_out');
    const guestCancelled = mkBooking('R102 Garden Single', guestId, 3, 5, 1, guestContact, 'cancelled');

    // ---- Payments ----
    insertPayment.run(guestFuture.id, guestFuture.total, '**** 4242', uid('TXN', 10));
    insertPayment.run(guestCheckedIn.id, guestCheckedIn.total, '**** 4242', uid('TXN', 10));
    insertPayment.run(guestCompleted.id, guestCompleted.total, '**** 1111', uid('TXN', 10));
    insertPayment.run(aliceBooking.id, aliceBooking.total, '**** 3333', uid('TXN', 10));
    insertPayment.run(bobCompleted.id, bobCompleted.total, '**** 5555', uid('TXN', 10));

    // ---- Invoices for completed stays ----
    insertInvoice.run(guestCompleted.id, uid('INV', 8), guestCompleted.total, now);
    insertInvoice.run(bobCompleted.id, uid('INV', 8), bobCompleted.total, now);

    // ---- Reviews ----
    insertReview.run(guestCompleted.id, guestId, 5, 'Lovely stay, clean room and helpful staff. The deluxe room was exactly as described.', 'published', moderatorId, now);
    insertReview.run(bobCompleted.id, bobId, 4, 'Great family suite, the kitchenette was handy. Noise from the street at night, otherwise perfect.', 'pending', null, null);

    // ---- Guest messages on the future booking ----
    insertMessage.run(guestFuture.id, guestId, 'Could we request an early check-in around 11am?', 'guest');
    insertMessage.run(guestFuture.id, staffId, 'Hello Grace, early check-in is confirmed if available; we will note the request on your booking.', 'staff');

    // ---- Outbound notifications (deterministic email adapter) ----
    insertNotification.run('guest@hotel.test', 'Welcome to Meridian Grand Hotel', 'Your account is ready. You can now search rooms and manage bookings.');
    insertNotification.run('guest@hotel.test', 'Booking confirmed ' + 'BK' + '…', `Your reservation is confirmed. Room: R101 Cozy Single. Dates: ${isoDate(10)} to ${isoDate(13)}.`);

    // ---- Account access + audit seed history ----
    insertAccessLog.run(guestId, 'register', 'Guest account created during seed');
    insertAccessLog.run(adminId, 'login', 'Admin seeded session established');
    insertAudit.run(staffId, 'create', 'availability_block', String(blockSuite), 'Floor refurbishment block created');
    insertAudit.run(staffId, 'create', 'availability_block', String(blockDeluxe), 'HVAC maintenance block created');

    return {
      userIds: { admin: adminId, staff: staffId, moderator: moderatorId, guest: guestId, alice: aliceId, bob: bobId },
      roomIds,
      bookingIds,
    };
  });

  return seedTx();
}
