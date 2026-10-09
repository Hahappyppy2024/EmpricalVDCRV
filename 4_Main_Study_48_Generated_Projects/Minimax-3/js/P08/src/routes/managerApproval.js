import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

const VALID_DECISIONS = new Set(['approved', 'rejected', 'changes_requested']);

function ensureManager(session, report) {
  const user = session.user;
  if (user.role !== 'manager' && user.role !== 'admin') {
    const e = new Error('Manager or admin role required'); e.status = 403; throw e;
  }
  if (user.role === 'manager') {
    const owner = db.prepare('SELECT manager_id FROM users WHERE id = ?').get(report.employee_id);
    if (owner?.manager_id !== user.id) {
      const e = new Error('Report not under your supervision'); e.status = 403; throw e;
    }
    if (report.employee_id === user.id) {
      const e = new Error('Self approval not allowed'); e.status = 403; throw e;
    }
  }
}

router.get('/manager_approval', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const user = session.user;
  if (user.role !== 'manager' && user.role !== 'admin') {
    return fail(res, 'Manager role required', 403, 'forbidden');
  }
  const status = req.query.status || 'submitted';
  let rows;
  if (user.role === 'admin') {
    rows = db.prepare('SELECT r.*, u.full_name AS employee_name, u.username AS employee_username FROM expense_reports r JOIN users u ON u.id = r.employee_id WHERE r.status = ? ORDER BY r.submitted_at ASC').all(status);
  } else {
    rows = db.prepare(`
      SELECT r.*, u.full_name AS employee_name, u.username AS employee_username
      FROM expense_reports r JOIN users u ON u.id = r.employee_id
      WHERE u.manager_id = ? AND r.status = ?
      ORDER BY r.submitted_at ASC
    `).all(user.id, status);
  }
  const approvals = db.prepare(`
    SELECT ma.*, u.username AS manager_username
    FROM manager_approvals ma JOIN users u ON u.id = ma.manager_id
    ORDER BY ma.created_at DESC LIMIT 50
  `).all();
  return ok(res, { reports: rows, history: approvals });
});

router.post('/manager_approval', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const { reportId, decision, note } = req.body || {};
  if (!reportId || !decision) return fail(res, 'reportId and decision required', 400, 'validation_error');
  if (!VALID_DECISIONS.has(decision)) return fail(res, `Invalid decision ${decision}`, 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(reportId));
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  try { ensureManager(session, report); }
  catch (e) { return fail(res, e.message, e.status, e.code); }
  if (report.status !== 'submitted') return fail(res, `Cannot decide on status ${report.status}`, 409, 'invalid_transition');

  const newStatus = ({
    approved: 'manager_approved',
    rejected: 'manager_rejected',
    changes_requested: 'changes_requested'
  })[decision];

  const tx = db.transaction(() => {
    db.prepare(`
      INSERT INTO manager_approvals (report_id, manager_id, decision, note)
      VALUES (?, ?, ?, ?)
    `).run(report.id, session.user.id, decision, note || null);

    db.prepare(`
      UPDATE expense_reports SET status = ?, manager_decision_at = datetime('now'), updated_at = datetime('now') WHERE id = ?
    `).run(newStatus, report.id);
    db.prepare(`
      INSERT INTO activity_logs (actor_id, report_id, event_type, details)
      VALUES (?, ?, 'manager_decision', ?)
    `).run(session.user.id, report.id, JSON.stringify({ decision, note }));
  });
  tx();
  return ok(res, { status: newStatus });
});

router.patch('/manager_approval/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const approval = db.prepare('SELECT * FROM manager_approvals WHERE id = ?').get(id);
  if (!approval) return fail(res, 'Not found', 404, 'not_found');
  if (approval.manager_id !== session.user.id && session.user.role !== 'admin') {
    return fail(res, 'Cannot modify', 403, 'forbidden');
  }
  const { note } = req.body || {};
  if (note === undefined) return fail(res, 'Nothing to update', 400, 'validation_error');
  db.prepare('UPDATE manager_approvals SET note = ? WHERE id = ?').run(note, id);
  return ok(res, { updated: true });
});

export default router;
