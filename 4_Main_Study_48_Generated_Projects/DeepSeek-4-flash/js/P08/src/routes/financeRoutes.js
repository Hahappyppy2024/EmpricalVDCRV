import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as approvalService from '../services/approvalService.js';

export function financeRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: { queue: approvalService.financeReviewQueue(db) } });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = approvalService.financeDecision(db, req.user, {
      reportId: Number(b.report_id),
      decision: b.decision,
      comment: b.comment,
    });
    res.json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const review = approvalService.updateFinanceReview(db, req.user, Number(req.params.id), {
      decision: b.decision,
      comment: b.comment,
    });
    res.json({ ok: true, data: review });
  });

  return router;
}
