import { resetDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { createApp } from '../src/app.js';
import http from 'node:http';

const PORT = 3102;
const BASE = `http://127.0.0.1:${PORT}`;

async function login(email, password) {
  const r = await fetch(`${BASE}/api/hotel/account_access`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'login', email, password }),
  });
  const setCookie = r.headers.getSetCookie ? r.headers.getSetCookie() : [];
  const sid = setCookie[0]?.split(';')[0] || '';
  return sid;
}

async function main() {
  resetDatabase();
  seedDatabase();
  const app = createApp();
  const server = http.createServer(app);
  await new Promise((resolve) => server.listen(PORT, '127.0.0.1', resolve));

  const checks = [];
  const grab = async (path, cookie, expectedStatus = 200) => {
    const r = await fetch(BASE + path, { headers: cookie ? { Cookie: cookie } : {} });
    const body = await r.text();
    const ok = r.status === expectedStatus && !body.includes('Cannot read properties') && !body.includes('is not defined');
    checks.push({ path, cookie: cookie ? 'yes' : 'no', status: r.status, ok });
    if (!ok) console.log(`  FAIL ${path} status=${r.status} len=${body.length}`);
  };

  const guest = await login('guest@hotel.test', 'guest123');
  const staff = await login('staff@hotel.test', 'staff123');
  const admin = await login('admin@hotel.test', 'admin123');
  const mod = await login('moderator@hotel.test', 'mod123');

  await grab('/');
  await grab('/account/login');
  await grab('/account/register');
  await grab('/account/recover');
  await grab('/account/reset?token=abc');
  await grab('/account', guest);
  await grab('/book', guest);
  await grab('/bookings', guest);
  await grab('/messages', guest);
  await grab('/reviews', guest);
  await grab('/invoices', guest);
  await grab('/staff/check-in', staff);
  await grab('/staff/inventory', staff);
  await grab('/admin/reports', admin);
  await grab('/errors', guest);
  await grab('/reviews', mod);
  await grab('/staff/check-in', guest, 403);

  const fails = checks.filter((c) => !c.ok);
  console.log(`\n[pages] ${checks.length - fails.length}/${checks.length} pages rendered OK`);
  server.close();
  closeDatabase();
  process.exit(fails.length ? 1 : 0);
}

main().catch((err) => {
  console.error('[pages] fatal:', err);
  closeDatabase();
  process.exit(1);
});
