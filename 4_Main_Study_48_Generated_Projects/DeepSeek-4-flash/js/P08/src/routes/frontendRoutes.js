import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as frontendService from '../services/frontendService.js';

export function frontendRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: frontendService.frontendState(db, req.user) });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    if (b.action === 'simulate_conflict') {
      const result = frontendService.simulateConflict(db, req.user, {
        reportId: Number(b.report_id),
        expectedStatus: b.expected_status,
      });
      return res.json({ ok: true, data: result });
    }
    const record = frontendService.reportClientError(db, req.user, {
      code: b.code,
      message: b.message,
      path: b.path,
      payload: b.payload,
    });
    res.status(201).json({ ok: true, data: record });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const record = frontendService.acknowledgeClientError(db, req.user, Number(req.params.id), { acknowledged: b.acknowledged });
    res.json({ ok: true, data: record });
  });

  return router;
}
