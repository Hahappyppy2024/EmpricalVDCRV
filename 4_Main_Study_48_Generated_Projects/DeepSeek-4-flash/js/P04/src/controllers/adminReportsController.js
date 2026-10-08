import { Router } from 'express';
import { runReport, generateReport, reportCsv, listReportExports } from '../services/reportService.js';
import { requireRole } from '../middleware/auth.js';
import { requireFields } from '../middleware/validation.js';
import { AppError } from '../middleware/errors.js';
import { getDb } from '../db/database.js';
import { nowSql } from '../services/sessionService.js';

const router = Router();

// GET /api/hotel/admin_reports
router.get('/', requireRole('admin'), (req, res) => {
  const reportType = req.query.type || 'occupancy';
  const filters = { from: req.query.from, to: req.query.to };
  const data = runReport(reportType, filters);
  if (req.query.format === 'csv') {
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', `attachment; filename="${reportType}-report.csv"`);
    return res.send(reportCsv(reportType, data));
  }
  res.json({ report_type: reportType, report: data, exports: listReportExports() });
});

// POST /api/hotel/admin_reports
router.post('/', requireRole('admin'), (req, res) => {
  requireFields(req.body, ['report_type']);
  const filters = req.body.filters || {};
  const outcome = generateReport({ adminId: req.user.id, reportType: req.body.report_type, filters });
  res.status(201).json(outcome);
});

// PATCH /api/hotel/admin_reports/:id  (annotate an export record)
router.patch('/:id', requireRole('admin'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const row = db.prepare('SELECT * FROM report_exports WHERE id = ?').get(id);
  if (!row) throw new AppError(404, 'EXPORT_NOT_FOUND', 'Report export record not found.');
  const note = req.body.note;
  if (note === undefined) throw new AppError(400, 'VALIDATION_ERROR', 'A note is required.');
  db.prepare('UPDATE report_exports SET note = ? WHERE id = ?').run(note, id);
  res.json({ ok: true, message: 'Export record annotated.' });
});

export default router;
