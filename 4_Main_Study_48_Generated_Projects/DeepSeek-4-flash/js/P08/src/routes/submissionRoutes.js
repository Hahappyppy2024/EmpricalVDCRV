import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as reportService from '../services/reportService.js';

export function submissionRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    const { status } = req.query;
    const reports = reportService.listVisibleReports(db, req.user, { status: status || 'submitted' });
    res.json({ ok: true, data: { submissions: reports, count: reports.length } });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = reportService.submitReport(db, req.user, { reportId: Number(b.report_id) });
    res.json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const reportId = Number(req.params.id);
    if (b.action === 'recall') {
      const result = reportService.recallReport(db, req.user, { reportId });
      return res.json({ ok: true, data: result });
    }
    res.json({ ok: true, data: { message: 'No action taken' } });
  });

  return router;
}
