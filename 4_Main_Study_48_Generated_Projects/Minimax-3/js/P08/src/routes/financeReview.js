import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

const VALID_DECISIONS = new Set(['approved', 'rejected', 'paid', 'on_hold']);

router.get('/finance_review', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const status = req.query.status || 'manager_approved';
  const reports = db.prepare(`
    SELECT r.*, u.full_name AS employee_name, u.username AS employee_username
    FROM expense_reports r JOIN users u ON u.id = r.employee_id
    WHERE r.status = ? ORDER BY r.manager_decision_at ASC
  `).all(status);
  const history = db.prepare(`
    SELECT fr.*, u.username AS finance_username, r.report_code
    FROM finance_reviews fr
    JOIN users u ON u.id = fr.finance_id
    JOIN expense_reports r ON r.id = fr.report_id
    ORDER BY fr.created_at DESC LIMIT 50
  `).all();
  return ok(res, { reports, history });
});

router.post('/finance_review', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const { reportId, decision, batchId, note } = req.body || {};
  if (!reportId || !decision) return fail(res, 'reportId and decision required', 400, 'validation_error');
  if (!VALID_DECISIONS.has(decision)) return fail(res, `Invalid decision ${decision}`, 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(reportId));
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  if (!['manager_approved', 'finance_approved'].includes(report.status)) {
    return fail(res, `Cannot review from status ${report.status}`, 409, 'invalid_transition');
  }

  const newStatus = ({ approved: 'finance_approved', rejected: 'finance_rejected', paid: 'paid', on_hold: 'finance_approved' })[decision];
  const finalBatch = batchId || (decision === 'paid' ? `BATCH-${Date.now().toString(36).toUpperCase()}` : null);

  const tx = db.transaction(() => {
    db.prepare(`
      INSERT INTO finance_reviews (report_id, finance_id, decision, batch_id, note)
      VALUES (?, ?, ?, ?, ?)
    `).run(report.id, session.user.id, decision, finalBatch, note || null);
    db.prepare(`UPDATE expense_reports SET status = ?, finance_decision_at = datetime('now'), updated_at = datetime('now'), reimbursement_at = CASE WHEN ? = 'paid' THEN datetime('now') ELSE reimbursement_at END WHERE id = ?`)
      .run(newStatus, decision, report.id);
    db.prepare(`
      INSERT INTO activity_logs (actor_id, report_id, event_type, details)
      VALUES (?, ?, 'finance_decision', ?)
    `).run(session.user.id, report.id, JSON.stringify({ decision, batchId: finalBatch, note }));
  });
  tx();
  return ok(res, { status: newStatus, batchId: finalBatch });
});

router.patch('/finance_review/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const id = Number(req.params.id);
  const review = db.prepare('SELECT * FROM finance_reviews WHERE id = ?').get(id);
  if (!review) return fail(res, 'Not found', 404, 'not_found');
  const { note } = req.body || {};
  if (note === undefined) return fail(res, 'Nothing to update', 400, 'validation_error');
  db.prepare('UPDATE finance_reviews SET note = ? WHERE id = ?').run(note, id);
  return ok(res, { updated: true });
});

export default router;
