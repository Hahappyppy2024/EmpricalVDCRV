import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import express from 'express';
import { config } from './config.js';
import { getDb } from './db/database.js';
import { createRealtime } from './ws/realtime.js';
import { AppError } from './lib/errors.js';

import { authRoutes } from './routes/authRoutes.js';
import { accountAccessRoutes } from './routes/accountAccessRoutes.js';
import { expenseReportRoutes } from './routes/expenseReportRoutes.js';
import { receiptRoutes } from './routes/receiptRoutes.js';
import { submissionRoutes } from './routes/submissionRoutes.js';
import { approvalRoutes } from './routes/approvalRoutes.js';
import { financeRoutes } from './routes/financeRoutes.js';
import { policyRoutes } from './routes/policyRoutes.js';
import { commentRoutes } from './routes/commentRoutes.js';
import { dataAccessRoutes } from './routes/dataAccessRoutes.js';
import { exportRoutes } from './routes/exportRoutes.js';
import { adminRoutes } from './routes/adminRoutes.js';
import { frontendRoutes } from './routes/frontendRoutes.js';
import { fileRoutes } from './routes/fileRoutes.js';
import { metaRoutes } from './routes/metaRoutes.js';

export function createApp() {
  getDb();

  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '2mb' }));
  app.use(express.urlencoded({ extended: true }));

  const httpServer = http.createServer(app);
  const { broadcast } = createRealtime(httpServer);

  app.use('/api/auth', authRoutes());
  app.use('/api/exp/account_access', accountAccessRoutes());
  app.use('/api/exp/expense_report_creation', expenseReportRoutes());
  app.use('/api/exp/receipt_upload', receiptRoutes());
  app.use('/api/exp/report_submission', submissionRoutes());
  app.use('/api/exp/manager_approval', approvalRoutes({ broadcast }));
  app.use('/api/exp/finance_review', financeRoutes());
  app.use('/api/exp/policy_rules', policyRoutes());
  app.use('/api/exp/comments_and_activity', commentRoutes({ broadcast }));
  app.use('/api/exp/employee_data_access', dataAccessRoutes());
  app.use('/api/exp/reimbursement_export', exportRoutes());
  app.use('/api/exp/admin_configuration', adminRoutes());
  app.use('/api/exp/frontend_api_integration_and_errors', frontendRoutes());
  app.use('/api/files', fileRoutes());
  app.use('/api/meta', metaRoutes());

  app.get('/api/health', (req, res) => {
    const db = getDb();
    const ok = db.prepare('SELECT 1 AS ok').get();
    res.json({ ok: true, data: { status: 'healthy', db: ok ? 'up' : 'down', time: new Date().toISOString() } });
  });

  app.use(express.static(config.publicDir));

  app.use((req, res, next) => {
    if (req.method === 'GET' && !req.path.startsWith('/api/')) {
      const file = path.join(config.publicDir, 'index.html');
      if (fs.existsSync(file)) return res.sendFile(file);
    }
    next();
  });

  app.use((req, res) => {
    res.status(404).json({ ok: false, error: { code: 'NOT_FOUND', message: 'Resource not found' } });
  });

  // eslint-disable-next-line no-unused-vars
  app.use((err, req, res, next) => {
    if (err instanceof AppError) {
      return res.status(err.status).json({ ok: false, error: { code: err.code, message: err.message, ...(err.details ? { details: err.details } : {}) } });
    }
    if (err && err.name === 'MulterError') {
      const msg = err.code === 'LIMIT_FILE_SIZE' ? `File exceeds the ${config.maxUploadBytes} byte limit` : `Upload failed: ${err.code}`;
      return res.status(400).json({ ok: false, error: { code: 'VALIDATION_ERROR', message: msg } });
    }
    if (err && err.type === 'entity.parse.failed') {
      return res.status(400).json({ ok: false, error: { code: 'VALIDATION_ERROR', message: 'Malformed JSON body' } });
    }
    console.error('[server] Unhandled error:', err);
    res.status(500).json({ ok: false, error: { code: 'INTERNAL_ERROR', message: 'An unexpected error occurred' } });
  });

  return { app, httpServer, broadcast };
}
