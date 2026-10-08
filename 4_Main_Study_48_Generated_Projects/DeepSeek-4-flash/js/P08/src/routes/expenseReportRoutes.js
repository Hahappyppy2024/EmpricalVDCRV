import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as reportService from '../services/reportService.js';

export function expenseReportRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    const { status, category_id: categoryId } = req.query;
    const reports = reportService.listVisibleReports(db, req.user, { status, categoryId });
    res.json({
      ok: true,
      data: {
        reports,
        filters: { status: status || null, category_id: categoryId || null },
        count: reports.length,
      },
    });
  });

  router.get('/:id', (req, res) => {
    const db = getDb();
    const report = reportService.reportDetail(db, Number(req.params.id));
    reportService.assertReportAccess(db, req.user, report);
    res.json({ ok: true, data: report });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const report = reportService.createReport(db, req.user, {
      title: b.title,
      purpose: b.purpose,
      departmentId: b.department_id,
      costCenterId: b.cost_center_id,
      lines: b.lines,
    });
    res.status(201).json({ ok: true, data: report });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const report = reportService.updateDraftReport(db, req.user, Number(req.params.id), {
      title: b.title,
      purpose: b.purpose,
      departmentId: b.department_id,
      costCenterId: b.cost_center_id,
      lines: b.lines,
    });
    res.json({ ok: true, data: report });
  });

  return router;
}
