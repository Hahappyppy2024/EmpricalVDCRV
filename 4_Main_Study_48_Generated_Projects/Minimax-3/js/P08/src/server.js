import express from 'express';
import cookieParser from 'cookie-parser';
import http from 'node:http';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

import { db, initSchema } from './db/index.js';
import { buildSessionMiddleware, purgeExpiredSessions } from './services/sessionService.js';

import accountAccess from './routes/accountAccess.js';
import expenseReportCreation from './routes/expenseReportCreation.js';
import receiptUpload from './routes/receiptUpload.js';
import reportSubmission from './routes/reportSubmission.js';
import managerApproval from './routes/managerApproval.js';
import financeReview from './routes/financeReview.js';
import policyRules from './routes/policyRules.js';
import commentsAndActivity from './routes/commentsAndActivity.js';
import employeeDataAccess from './routes/employeeDataAccess.js';
import reimbursementExport from './routes/reimbursementExport.js';
import adminConfiguration from './routes/adminConfiguration.js';
import frontendApiIntegration from './routes/frontendApiIntegration.js';
import { setupWebSocket } from './services/websocketService.js';

initSchema();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '0.0.0.0';
const APP_TITLE = process.env.APP_TITLE || 'Enterprise Expense Approval System';

const publicDir = path.join(projectRoot, 'public');

if (!fs.existsSync(path.join(projectRoot, 'data'))) fs.mkdirSync(path.join(projectRoot, 'data'), { recursive: true });
if (!fs.existsSync(publicDir)) fs.mkdirSync(publicDir, { recursive: true });

const app = express();
app.disable('x-powered-by');
app.use(express.urlencoded({ extended: false }));
app.use(cookieParser());
app.use(buildSessionMiddleware());

app.use((req, _res, next) => {
  req.db = db;
  next();
});

app.get('/api/health', (_req, res) => {
  const tables = db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'").all().map(r => r.name);
  res.json({ ok: true, app: APP_TITLE, tables, timestamp: new Date().toISOString() });
});

app.use('/api/exp', accountAccess);
app.use('/api/exp', expenseReportCreation);
app.use('/api/exp', receiptUpload);
app.use('/api/exp', reportSubmission);
app.use('/api/exp', managerApproval);
app.use('/api/exp', financeReview);
app.use('/api/exp', policyRules);
app.use('/api/exp', commentsAndActivity);
app.use('/api/exp', employeeDataAccess);
app.use('/api/exp', reimbursementExport);
app.use('/api/exp', adminConfiguration);
app.use('/api/exp', frontendApiIntegration);

app.get('/', (_req, res) => {
  res.sendFile(path.join(publicDir, 'index.html'));
});
app.get('/app', (_req, res) => {
  res.sendFile(path.join(publicDir, 'app.html'));
});
app.use(express.static(publicDir));

app.use((req, res) => {
  res.status(404).json({ ok: false, error: { code: 'not_found', message: `No route for ${req.method} ${req.path}` } });
});

app.use((err, _req, res, _next) => {
  console.error('Unhandled error:', err?.message || err);
  if (res.headersSent) return;
  res.status(500).json({ ok: false, error: { code: 'server_error', message: err?.message || 'Internal error' } });
});

const server = http.createServer(app);
setupWebSocket(server);

setInterval(() => {
  try { purgeExpiredSessions(); } catch (_) { /* ignore */ }
}, 60 * 1000);

server.listen(PORT, HOST, () => {
  console.log(`${APP_TITLE} running on http://${HOST}:${PORT}`);
});

export { app, server };
