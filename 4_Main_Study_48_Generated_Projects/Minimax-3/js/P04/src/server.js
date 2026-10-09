import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { env } from './config.js';
import { getDb, runSchema } from './db/connection.js';
import { loadAuth } from './middleware/auth.js';
import { parseJsonBody } from './services/http.js';
import { purgeExpired } from './services/session.js';
import { registerAccountRoutes } from './routes/account.js';
import { registerRoomRoutes } from './routes/rooms_search.js';
import { registerRoomDetailsRoutes } from './routes/room_details.js';
import { registerBookingCreationRoutes } from './routes/booking_creation.js';
import { registerBookingManagementRoutes } from './routes/booking_management.js';
import { registerStaffCheckRoutes } from './routes/staff_check.js';
import { registerRoomInventoryRoutes } from './routes/room_inventory.js';
import { registerGuestMessagesRoutes } from './routes/messages.js';
import { registerReviewsRoutes } from './routes/reviews.js';
import { registerInvoiceRoutes } from './routes/invoice.js';
import { registerAdminReportsRoutes } from './routes/admin_reports.js';
import { registerFrontendApiIntegrationRoutes } from './routes/frontend_api.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
app.disable('x-powered-by');

const db = getDb();
runSchema(db);
purgeExpired();

app.use(async (req, res, next) => {
  try {
    req.body = await parseJsonBody(req);
    next();
  } catch (e) {
    res.statusCode = 400;
    res.setHeader('content-type', 'application/json');
    res.end(JSON.stringify({ error: 'bad_request', message: e.message }));
  }
});

const cookie = (req, _res, next) => {
  req.cookies = parseCookies(req.headers.cookie);
  next();
};

app.use(cookie);
app.use(loadAuth);

const publicDir = path.resolve(__dirname, '..', 'public');
app.use(express.static(publicDir));

app.get('/', (req, res) => res.redirect('/pages/index.html'));

registerAccountRoutes(app);
registerRoomRoutes(app);
registerRoomDetailsRoutes(app);
registerBookingCreationRoutes(app);
registerBookingManagementRoutes(app);
registerStaffCheckRoutes(app);
registerRoomInventoryRoutes(app);
registerGuestMessagesRoutes(app);
registerReviewsRoutes(app);
registerInvoiceRoutes(app);
registerAdminReportsRoutes(app);
registerFrontendApiIntegrationRoutes(app);

app.get('/api/health', (req, res) => {
  res.setHeader('content-type', 'application/json');
  res.end(JSON.stringify({ status: 'ok', hotel: env().HOTEL_NAME, time: new Date().toISOString() }));
});

app.use((req, res) => {
  if (req.path.startsWith('/api/')) {
    res.statusCode = 404;
    res.setHeader('content-type', 'application/json');
    return res.end(JSON.stringify({ error: 'not_found', message: `No route for ${req.path}` }));
  }
  res.statusCode = 404;
  res.end('Not found');
});

app.use((err, req, res, next) => {
  console.error('[error]', err);
  res.statusCode = 500;
  res.setHeader('content-type', 'application/json');
  res.end(JSON.stringify({ error: 'internal_error', message: 'Unexpected server error' }));
});

const cfg = env();
const server = app.listen(cfg.PORT, () => {
  console.log(`[hbs] ${cfg.HOTEL_NAME} listening on ${cfg.APP_BASE_URL}`);
});

process.on('SIGINT', () => server.close(() => process.exit(0)));
process.on('SIGTERM', () => server.close(() => process.exit(0)));

function parseCookies(header) {
  const out = {};
  if (!header) return out;
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx < 0) continue;
    const k = part.slice(0, idx).trim();
    const v = part.slice(idx + 1).trim();
    out[k] = decodeURIComponent(v);
  }
  return out;
}