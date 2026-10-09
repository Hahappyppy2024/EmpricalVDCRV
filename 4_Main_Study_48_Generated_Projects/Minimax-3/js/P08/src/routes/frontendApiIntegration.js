import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

router.get('/frontend_api_integration_and_errors', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const lastEvents = db.prepare(`
    SELECT a.event_type, a.report_id, a.details, a.created_at, u.username
    FROM activity_logs a
    LEFT JOIN users u ON u.id = a.actor_id
    ORDER BY a.created_at DESC LIMIT 20
  `).all();
  const errorReports = db.prepare(`
    SELECT * FROM expense_reports WHERE status IN ('manager_rejected','finance_rejected')
    ORDER BY updated_at DESC LIMIT 20
  `).all();
  return ok(res, { recentEvents: lastEvents, rejectedReports: errorReports });
});

router.post('/frontend_api_integration_and_errors', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const { reportId, code, uiMessage } = req.body || {};
  if (!reportId || !code) return fail(res, 'reportId and code required', 400, 'validation_error');
  const validCodes = ['policy_warning', 'validation_error', 'conflict', 'network_error', 'approval_acknowledged'];
  if (!validCodes.includes(code)) return fail(res, 'Unknown response state code', 400, 'validation_error');
  db.prepare(`
    INSERT INTO activity_logs (actor_id, report_id, event_type, details)
    VALUES (?, ?, 'frontend_api_state', ?)
  `).run(session.user.id, Number(reportId), JSON.stringify({ code, uiMessage: uiMessage || null }));
  return ok(res, { logged: true, code });
});

export default router;
