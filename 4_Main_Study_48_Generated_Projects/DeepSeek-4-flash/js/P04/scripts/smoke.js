import { resetDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { createApp } from '../src/app.js';
import { attachWs } from '../src/services/wsHub.js';
import http from 'node:http';

const PORT = 3101;
const BASE = `http://127.0.0.1:${PORT}`;

function cookies() {
  const jar = new Map();
  const read = (headers) => {
    const setCookie = headers.getSetCookie ? headers.getSetCookie() : [];
    for (const line of setCookie) {
      const [pair] = line.split(';');
      const idx = pair.indexOf('=');
      if (idx === -1) continue;
      jar.set(pair.slice(0, idx).trim(), pair.slice(idx + 1).trim());
    }
  };
  const header = () => [...jar.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
  return { read, header };
}

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  — ${detail}` : ''}`);
}

async function req(path, { method = 'GET', body, cookieHeader, format } = {}) {
  const response = await fetch(BASE + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(cookieHeader ? { Cookie: cookieHeader } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data;
  const ct = response.headers.get('content-type') || '';
  if (ct.includes('application/json')) data = await response.json();
  else if (ct.includes('text/csv')) data = await response.text();
  else data = await response.text();
  return { status: response.status, data, headers: response.headers };
}

async function main() {
  resetDatabase();
  seedDatabase();

  const app = createApp();
  const server = http.createServer(app);
  attachWs(server);
  await new Promise((resolve) => server.listen(PORT, '127.0.0.1', resolve));
  console.log(`[smoke] server on ${BASE}\n`);

  // 1. Health
  {
    const r = await req('/api/health');
    check('HOTEL-00 health endpoint', r.status === 200 && r.data.status === 'ok');
  }

  // 2. Unauthenticated access → 401
  {
    const r = await req('/api/hotel/booking_management');
    check('HOTEL-01-FA-03 unauthenticated rejected', r.status === 401 && r.data.error.code === 'UNAUTHORIZED');
  }

  // 3. Guest login
  const guest = cookies();
  {
    const r = await req('/api/hotel/account_access', {
      method: 'POST',
      body: { action: 'login', email: 'guest@hotel.test', password: 'guest123' },
    });
    guest.read(r.headers);
    check('HOTEL-01-FA-01 valid sign-in', r.status === 200 && r.data.user.role === 'guest', `redirect=${r.data.redirect}`);
  }
  const guestCookie = guest.header();

  // 4. Wrong credentials rejected
  {
    const r = await req('/api/hotel/account_access', {
      method: 'POST',
      body: { action: 'login', email: 'guest@hotel.test', password: 'wrong-password' },
    });
    check('HOTEL-01-FA-02 invalid credentials rejected', r.status === 401 && r.data.error.code === 'INVALID_CREDENTIALS');
  }

  // 5. Room search (HOTEL-02)
  {
    const r = await req('/api/hotel/room_search?capacity=2&type=suite', { cookieHeader: guestCookie });
    check('HOTEL-02-FA-01 known filters return matching rooms', r.status === 200 && r.data.count >= 1 && r.data.results.every((room) => room.capacity >= 2));
  }
  {
    const r = await req('/api/hotel/room_search?capacity=8&min_price=1000', { cookieHeader: guestCookie });
    check('HOTEL-02-FA-02 empty filters return bounded empty response', r.status === 200 && r.data.count === 0 && Array.isArray(r.data.results));
  }

  // 6. Room details (HOTEL-03)
  {
    const r = await req('/api/hotel/room_details?id=1', { cookieHeader: guestCookie });
    check('HOTEL-03-FA-01 room details view', r.status === 200 && r.data.room.id === 1);
  }
  {
    const r = await req('/api/hotel/room_details?id=99999', { cookieHeader: guestCookie });
    check('HOTEL-03 unknown room rejected', r.status === 404 && r.data.error.code === 'ROOM_NOT_FOUND');
  }

  // 7. Booking creation (HOTEL-04)
  const future = new Date(Date.now() + 30 * 86400000);
  const future2 = new Date(Date.now() + 32 * 86400000);
  const iso = (d) => d.toISOString().slice(0, 10);
  const bookingPayload = {
    room_id: 1,
    check_in_date: iso(future),
    check_out_date: iso(future2),
    guests: 1,
    contact_name: 'Grace Guest',
    contact_email: 'guest@hotel.test',
    contact_phone: '+1 555 0201',
    card_number: '4242424242424242',
    card_expiry: '12/29',
    card_cvc: '123',
    idempotency_key: 'smoke-booking-1',
  };
  let bookingId;
  {
    const r = await req('/api/hotel/booking_creation', { method: 'POST', body: bookingPayload, cookieHeader: guestCookie });
    bookingId = r.data.booking.id;
    check('HOTEL-04-FA-01 booking created', r.status === 201 && r.data.booking.status === 'confirmed', `code=${r.data.booking.code}`);
  }
  {
    const r2 = await req('/api/hotel/booking_creation', { method: 'POST', body: bookingPayload, cookieHeader: guestCookie });
    check('HOTEL-04 idempotency key prevents duplicates', r2.status === 200 && r2.data.duplicate === true && r2.data.booking.id === bookingId);
  }
  {
    const conflictPayload = { ...bookingPayload, room_id: 1, idempotency_key: 'smoke-booking-2' };
    const r = await req('/api/hotel/booking_creation', { method: 'POST', body: conflictPayload, cookieHeader: guestCookie });
    check('HOTEL-04 unavailable dates rejected (409)', r.status === 409 && r.data.error.code === 'ROOM_UNAVAILABLE');
  }
  {
    const bad = { ...bookingPayload, check_in_date: 'not-a-date', check_out_date: 'nope', idempotency_key: 'smoke-booking-3' };
    const r = await req('/api/hotel/booking_creation', { method: 'POST', body: bad, cookieHeader: guestCookie });
    check('HOTEL-04-FA-02 invalid input rejected without persistence', r.status === 400 && r.data.error.code === 'VALIDATION_ERROR');
  }

  // 8. Booking management (HOTEL-05)
  {
    const r = await req('/api/hotel/booking_management', { cookieHeader: guestCookie });
    check('HOTEL-05 list own bookings', r.status === 200 && r.data.bookings.length >= 1);
  }
  {
    const alice = cookies();
    const r1 = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'alice@hotel.test', password: 'alice123' } });
    alice.read(r1.headers);
    const r = await req(`/api/hotel/booking_management/${bookingId}`, { method: 'PATCH', body: { action: 'cancel' }, cookieHeader: alice.header() });
    check('HOTEL-05-FA-03 non-owner cannot cancel', r.status === 403 && r.data.error.code === 'FORBIDDEN');
  }
  {
    const r = await req(`/api/hotel/booking_management/${bookingId}`, { method: 'PATCH', body: { action: 'cancel' }, cookieHeader: guestCookie });
    check('HOTEL-05-FA-01 owner cancels booking', r.status === 200 && r.data.booking.status === 'cancelled');
  }
  {
    const r = await req(`/api/hotel/booking_management/${bookingId}`, { method: 'PATCH', body: { action: 'cancel' }, cookieHeader: guestCookie });
    check('HOTEL-05-FA-02 invalid state transition rejected', r.status === 409 && r.data.error.code === 'INVALID_STATE');
  }

  // 9. Staff check-in/out (HOTEL-06)
  const staff = cookies();
  {
    const r = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'staff@hotel.test', password: 'staff123' } });
    staff.read(r.headers);
  }
  const staffCookie = staff.header();

  // Create a booking that is due for check-in today so staff can process it.
  const today = new Date();
  const stayBookingPayload = {
    room_id: 2,
    check_in_date: iso(today),
    check_out_date: iso(new Date(today.getTime() + 3 * 86400000)),
    guests: 2,
    contact_name: 'Grace Guest',
    contact_email: 'guest@hotel.test',
    contact_phone: '+1 555 0201',
    card_number: '4242424242424242',
    card_expiry: '12/29',
    card_cvc: '123',
    idempotency_key: 'smoke-stay-booking',
  };
  const stayBooking = await req('/api/hotel/booking_creation', { method: 'POST', body: stayBookingPayload, cookieHeader: guestCookie });
  const stayBookingId = stayBooking.data.booking.id;
  check('setup: stay booking created', stayBooking.status === 201 && stayBookingId > 0);

  let checkInBookingId = null;
  {
    const r = await req('/api/hotel/staff_check_in_out', { cookieHeader: staffCookie });
    check('HOTEL-06 list due for check-in', r.status === 200 && r.data.due_for_check_in.some((b) => b.id === stayBookingId));
    checkInBookingId = stayBookingId;
  }
  {
    const r = await req('/api/hotel/staff_check_in_out', { method: 'POST', body: { booking_id: checkInBookingId, action: 'check_in' }, cookieHeader: staffCookie });
    check('HOTEL-06-FA-01 check-in', r.status === 200 && r.data.booking.status === 'checked_in');
  }
  {
    const r = await req('/api/hotel/staff_check_in_out', { method: 'POST', body: { booking_id: checkInBookingId, action: 'check_in' }, cookieHeader: staffCookie });
    check('HOTEL-06-FA-02 invalid transition rejected', r.status === 409 && r.data.error.code === 'INVALID_STATE');
  }
  {
    const r = await req('/api/hotel/staff_check_in_out', { method: 'POST', body: { booking_id: checkInBookingId, action: 'check_out' }, cookieHeader: staffCookie });
    check('HOTEL-06 checkout completes stay', r.status === 200 && r.data.booking.status === 'checked_out');
  }
  {
    const guestB = cookies();
    const r1 = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'guest@hotel.test', password: 'guest123' } });
    guestB.read(r1.headers);
    const r = await req('/api/hotel/staff_check_in_out', { cookieHeader: guestB.header() });
    check('HOTEL-06-FA-03 non-staff rejected', r.status === 403);
  }

  // 10. Room inventory (HOTEL-07)
  {
    const r = await req('/api/hotel/room_inventory', { cookieHeader: staffCookie });
    check('HOTEL-07 list rooms + blocks', r.status === 200 && r.data.rooms.length === 10 && r.data.availability_blocks.length >= 1);
  }
  {
    const r = await req('/api/hotel/room_inventory', { method: 'POST', body: { entity: 'room', name: 'R777 Smoke Test Suite', type: 'suite', price_per_night: 199, capacity: 2, description: 'Created by smoke test.', amenities: ['Wi-Fi'] }, cookieHeader: staffCookie });
    check('HOTEL-07-FA-01 create room', r.status === 201 && r.data.room.name === 'R777 Smoke Test Suite');
  }
  {
    const r = await req('/api/hotel/room_inventory', { method: 'POST', body: { entity: 'room', name: 'R777 Smoke Test Suite', type: 'suite', price_per_night: 199, capacity: 2, description: 'Duplicate.' }, cookieHeader: staffCookie });
    check('HOTEL-07-FA-02 duplicate room rejected', r.status === 409 && r.data.error.code === 'ROOM_EXISTS');
  }

  // 11. Guest messages (HOTEL-08)
  {
    const r = await req('/api/hotel/guest_messages', { method: 'POST', body: { booking_id: 1, body: 'Please add an extra pillow.' }, cookieHeader: guestCookie });
    check('HOTEL-08-FA-01 guest message sent', r.status === 201 && r.data.message.sender_role === 'guest');
  }
  {
    const r = await req('/api/hotel/guest_messages', { method: 'POST', body: { booking_id: 1, body: 'Confirmed, extra pillow noted.' }, cookieHeader: staffCookie });
    check('HOTEL-08 staff reply', r.status === 201 && r.data.message.sender_role === 'staff');
  }
  {
    const alice = cookies();
    const r1 = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'alice@hotel.test', password: 'alice123' } });
    alice.read(r1.headers);
    const r = await req('/api/hotel/guest_messages', { method: 'POST', body: { booking_id: 1, body: 'Not my booking.' }, cookieHeader: alice.header() });
    check('HOTEL-08-FA-03 cross-user message rejected', r.status === 403 && r.data.error.code === 'FORBIDDEN');
  }

  // 12. Reviews (HOTEL-09)
  const bob = cookies();
  {
    const r = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'bob@hotel.test', password: 'bob123' } });
    bob.read(r.headers);
  }
  let pendingReviewId = null;
  {
    const r = await req('/api/hotel/reviews', { cookieHeader: bob.header() });
    pendingReviewId = r.data.reviews.find((rv) => rv.status === 'pending')?.id;
    check('HOTEL-09 pending review visible to owner', r.status === 200 && pendingReviewId != null);
  }
  const mod = cookies();
  {
    const r = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'moderator@hotel.test', password: 'mod123' } });
    mod.read(r.headers);
  }
  {
    const r = await req(`/api/hotel/reviews/${pendingReviewId}`, { method: 'PATCH', body: { status: 'published' }, cookieHeader: mod.header() });
    check('HOTEL-09 moderator publishes review', r.status === 200 && r.data.review.status === 'published');
  }
  {
    const r = await req(`/api/hotel/reviews/${pendingReviewId}`, { method: 'PATCH', body: { status: 'published' }, cookieHeader: guestCookie });
    check('HOTEL-09-FA-03 non-moderator rejected', r.status === 403);
  }

  // 13. Invoices (HOTEL-10)
  {
    // stayBooking was checked out in the staff section and has no invoice yet.
    const r = await req('/api/hotel/invoice_and_receipt', { method: 'POST', body: { booking_id: stayBookingId }, cookieHeader: guestCookie });
    check('HOTEL-10-FA-01 invoice generated for completed stay', r.status === 201 && r.data.invoice.total > 0 && r.data.duplicate === false);
  }
  {
    const r = await req('/api/hotel/invoice_and_receipt', { method: 'POST', body: { booking_id: stayBookingId }, cookieHeader: guestCookie });
    check('HOTEL-10 duplicate invoice prevented', r.status === 200 && r.data.duplicate === true);
  }
  {
    const r = await req('/api/hotel/invoice_and_receipt', { method: 'POST', body: { booking_id: 3 }, cookieHeader: guestCookie });
    check('HOTEL-10 seeded completed stay has invoice', r.status === 200 && r.data.duplicate === true);
  }
  {
    const r = await req('/api/hotel/invoice_and_receipt?format=csv', { cookieHeader: guestCookie });
    check('HOTEL-10 CSV export', r.status === 200 && typeof r.data === 'string' && r.data.includes('Invoice Number'));
  }

  // 14. Admin reports (HOTEL-11)
  const admin = cookies();
  {
    const r = await req('/api/hotel/account_access', { method: 'POST', body: { action: 'login', email: 'admin@hotel.test', password: 'admin123' } });
    admin.read(r.headers);
  }
  const adminCookie = admin.header();
  {
    const r = await req('/api/hotel/admin_reports?type=occupancy', { cookieHeader: adminCookie });
    check('HOTEL-11 occupancy report', r.status === 200 && r.data.report.total_rooms >= 10);
  }
  {
    const r = await req('/api/hotel/admin_reports?type=revenue', { cookieHeader: adminCookie });
    check('HOTEL-11 revenue report', r.status === 200 && typeof r.data.report.total_revenue === 'number');
  }
  {
    const r = await req('/api/hotel/admin_reports', { method: 'POST', body: { report_type: 'cancellations', filters: { from: '2000-01-01', to: '2100-12-31' } }, cookieHeader: adminCookie });
    check('HOTEL-11-FA-01 report export recorded + audited', r.status === 201 && r.data.export_id > 0);
  }
  {
    const r = await req('/api/hotel/admin_reports?type=occupancy', { cookieHeader: staffCookie });
    check('HOTEL-11-FA-03 non-admin rejected', r.status === 403);
  }

  // 15. Frontend API integration & errors (HOTEL-12)
  {
    const r = await req('/api/hotel/frontend_api_integration_and_errors', { method: 'POST', body: { action: 'simulate', scenario: 'unavailable' }, cookieHeader: guestCookie });
    check('HOTEL-12 unavailable-date scenario', r.status === 200 && r.data.simulated.kind === 'unavailable_dates');
  }
  {
    const r = await req('/api/hotel/frontend_api_integration_and_errors', { method: 'POST', body: { action: 'simulate', scenario: 'validation' }, cookieHeader: guestCookie });
    check('HOTEL-12 validation scenario', r.status === 200 && r.data.simulated.code === 'VALIDATION_ERROR');
  }
  {
    const r = await req('/api/hotel/frontend_api_integration_and_errors', { method: 'POST', body: { context: 'date-picker', payload: { source: 'smoke-test' } }, cookieHeader: guestCookie });
    check('HOTEL-12-FA-01 error report recorded', r.status === 201 && r.data.report.id > 0);
  }

  // 16. WebSocket connectivity
  {
    const ws = await import('ws');
    const socket = new ws.WebSocket(`ws://127.0.0.1:${PORT}/ws`);
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('ws timeout')), 3000);
      socket.on('open', () => { clearTimeout(timer); resolve(); });
      socket.on('error', reject);
    });
    check('HOTEL-06/08 WebSocket adapter accepts connections', true);
    socket.close();
  }

  // 17. Public page renders
  {
    const r = await req('/search?type=suite', { format: 'html' });
    check('Page /search renders', r.status === 200 && r.data.includes('Search rooms'));
  }
  {
    const r = await req('/rooms/1', { format: 'html' });
    check('Page /rooms/1 renders', r.status === 200 && r.data.includes('R101 Cozy Single'));
  }

  console.log(`\n[smoke] ${results.filter((r) => r.ok).length}/${results.length} checks passed`);
  server.close();
  closeDatabase();
  process.exit(results.every((r) => r.ok) ? 0 : 1);
}

main().catch((err) => {
  console.error('[smoke] fatal error:', err);
  closeDatabase();
  process.exit(1);
});
