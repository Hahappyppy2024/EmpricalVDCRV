import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as approvalService from '../services/approvalService.js';

export function commentRoutes({ broadcast }) {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    const reportId = Number(req.query.report_id);
    if (!reportId) {
      return res.status(400).json({ ok: false, error: { code: 'VALIDATION_ERROR', message: 'report_id is required' } });
    }
    res.json({
      ok: true,
      data: {
        report_id: reportId,
        comments: approvalService.listComments(db, req.user, reportId),
        activity: approvalService.listActivity(db, req.user, reportId),
      },
    });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = approvalService.addComment(db, req.user, { reportId: Number(b.report_id), body: b.body });
    if (broadcast) {
      broadcast({ type: 'comment.created', data: { ...result.comment, report_no: result.report_no } });
    }
    res.status(201).json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const comment = approvalService.updateComment(db, req.user, Number(req.params.id), { body: b.body });
    res.json({ ok: true, data: comment });
  });

  return router;
}
