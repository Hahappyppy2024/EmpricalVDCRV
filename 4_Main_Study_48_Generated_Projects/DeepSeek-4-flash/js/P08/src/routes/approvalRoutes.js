import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as approvalService from '../services/approvalService.js';

export function approvalRoutes({ broadcast }) {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: { queue: approvalService.managerApprovalQueue(db, req.user) } });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = approvalService.managerDecision(db, req.user, {
      reportId: Number(b.report_id),
      decision: b.decision,
      comment: b.comment,
    });
    if (broadcast) {
      broadcast({ type: 'approval.decision', data: { report_id: Number(b.report_id), decision: b.decision, actor: req.user.full_name } });
    }
    res.json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const approval = approvalService.updateApproval(db, req.user, Number(req.params.id), {
      decision: b.decision,
      comment: b.comment,
    });
    res.json({ ok: true, data: approval });
  });

  return router;
}
