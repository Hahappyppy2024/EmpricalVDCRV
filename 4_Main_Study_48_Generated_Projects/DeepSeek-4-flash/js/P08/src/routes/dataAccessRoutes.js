import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as approvalService from '../services/approvalService.js';

export function dataAccessRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({
      ok: true,
      data: {
        employees: approvalService.listVisibleEmployees(db, req.user),
        accesses: approvalService.listDataAccesses(db, req.user),
      },
    });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = approvalService.recordDataAccess(db, req.user, {
      employeeId: Number(b.employee_id),
      reportId: Number(b.report_id),
      action: b.action || 'view',
      note: b.note,
    });
    res.status(201).json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const access = approvalService.updateDataAccess(db, req.user, Number(req.params.id), { note: b.note });
    res.json({ ok: true, data: access });
  });

  return router;
}
