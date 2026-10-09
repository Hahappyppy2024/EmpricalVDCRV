import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

const REPORT_TRANSITIONS = {
  draft: ['submitted'],
  submitted: ['manager_approved', 'manager_rejected', 'changes_requested'],
  changes_requested: ['submitted'],
  manager_approved: ['finance_approved', 'finance_rejected'],
  finance_approved: ['reimbursed', 'paid'],
  finance_rejected: [],
  manager_rejected: [],
  reimbursed: [],
  paid: []
};

function getReportPolicyStatus(report) {
  const rules = db.prepare('SELECT * FROM policy_rules WHERE active = 1').all();
  const lines = db.prepare(`
    SELECT el.amount, el.category_id, c.code AS category_code
    FROM expense_lines el
    LEFT JOIN categories c ON c.id = el.category_id
    WHERE el.report_id = ?
  `).all(report.id);
  const violations = [];
  const warnings = [];
  for (const line of lines) {
    for (const r of rules) {
      if (r.category_id && r.category_id !== line.category_id) continue;
      if (r.max_amount != null && line.amount > r.max_amount) {
        if (r.block_when_exceeded) violations.push({ rule: r.name, lineAmount: line.amount, cap: r.max_amount });
        else warnings.push({ rule: r.name, lineAmount: line.amount, cap: r.max_amount });
      }
      if (r.require_receipt_above && line.amount >= r.require_receipt_above) {
        const hasReceipt = db.prepare('SELECT 1 FROM stored_files WHERE related_report_id = ? AND context = \'receipt\' AND (related_line_id IS NULL OR related_line_id IN (SELECT id FROM expense_lines WHERE report_id = ?))').get(report.id, report.id);
        if (!hasReceipt) warnings.push({ rule: r.name + ' (missing receipt)', lineAmount: line.amount });
      }
    }
  }
  return { violations, warnings };
}

router.get('/report_submission', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const status = req.query.status || 'submitted';
  let rows;
  if (session.user.role === 'employee') {
    rows = db.prepare('SELECT * FROM expense_reports WHERE employee_id = ? AND status = ? ORDER BY created_at DESC').all(session.user.id, status);
  } else if (session.user.role === 'manager') {
    rows = db.prepare(`
      SELECT r.* FROM expense_reports r
      JOIN users u ON u.id = r.employee_id
      WHERE u.manager_id = ? AND r.status = ?
      ORDER BY r.created_at DESC
    `).all(session.user.id, status);
  } else {
    rows = db.prepare('SELECT * FROM expense_reports WHERE status = ? ORDER BY created_at DESC').all(status);
  }
  return ok(res, { reports: rows });
});

router.post('/report_submission', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const { reportId, action } = req.body || {};
  if (!reportId || !action) return fail(res, 'reportId and action are required', 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(reportId));
  if (!report) return fail(res, 'Report not found', 404, 'not_found');
  const user = session.user;
  if (user.role !== 'employee' && user.role !== 'admin') {
    return fail(res, 'Only employees can submit their own reports', 403, 'forbidden');
  }
  if (report.employee_id !== user.id && user.role !== 'admin') {
    return fail(res, 'Not your report', 403, 'forbidden');
  }

  if (action === 'submit') {
    if (!REPORT_TRANSITIONS[report.status]?.includes('submitted')) {
      return fail(res, `Cannot submit from status ${report.status}`, 409, 'invalid_transition');
    }
    const lines = db.prepare('SELECT COUNT(*) AS n FROM expense_lines WHERE report_id = ?').get(report.id);
    if (!lines.n) return fail(res, 'Cannot submit empty report', 400, 'validation_error');
    const policy = getReportPolicyStatus(report);
    if (policy.violations.length) {
      return fail(res, 'Policy violations present', 409, 'policy_blocked', policy);
    }
    db.prepare(`
      UPDATE expense_reports
      SET status = 'submitted', submitted_at = datetime('now'), updated_at = datetime('now')
      WHERE id = ?
    `).run(report.id);
    db.prepare(`
      INSERT INTO activity_logs (actor_id, report_id, event_type, details)
      VALUES (?, ?, 'report_submitted', ?)
    `).run(user.id, report.id, JSON.stringify({ warnings: policy.warnings }));
    return ok(res, { status: 'submitted', warnings: policy.warnings });
  }

  if (action === 'withdraw') {
    if (report.status !== 'submitted') return fail(res, `Cannot withdraw from status ${report.status}`, 409, 'invalid_transition');
    db.prepare(`UPDATE expense_reports SET status = 'draft', submitted_at = NULL WHERE id = ?`).run(report.id);
    db.prepare(`INSERT INTO activity_logs (actor_id, report_id, event_type) VALUES (?, ?, 'report_withdrawn')`).run(user.id, report.id);
    return ok(res, { status: 'draft' });
  }

  return fail(res, `Unknown action ${action}`, 400, 'validation_error');
});

router.patch('/report_submission/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(id);
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  if (report.employee_id !== session.user.id && session.user.role !== 'admin') {
    return fail(res, 'Not your report', 403, 'forbidden');
  }
  if (report.status !== 'changes_requested') {
    return fail(res, `Cannot update from ${report.status}`, 409, 'invalid_transition');
  }
  const { lines, title, description } = req.body || {};
  if (Array.isArray(lines) || title || description) {
    const tx = db.transaction(() => {
      if (title || description !== undefined) {
        const u = []; const v = [];
        if (title) { u.push('title = ?'); v.push(title); }
        if (description !== undefined) { u.push('description = ?'); v.push(description); }
        u.push("updated_at = datetime('now')");
        v.push(id);
        db.prepare(`UPDATE expense_reports SET ${u.join(', ')} WHERE id = ?`).run(...v);
      }
      if (Array.isArray(lines)) {
        db.prepare('DELETE FROM expense_lines WHERE report_id = ?').run(id);
        const ins = db.prepare('INSERT INTO expense_lines (report_id, category_id, description, expense_date, amount, currency, merchant) VALUES (?, ?, ?, ?, ?, ?, ?)');
        for (const l of lines) {
          if (!l || !l.description || !l.expenseDate || l.amount == null) continue;
          const cat = l.categoryCode ? db.prepare('SELECT id FROM categories WHERE code = ?').get(String(l.categoryCode)) : null;
          ins.run(id, cat?.id || null, String(l.description), String(l.expenseDate), Number(l.amount), l.currency || 'USD', l.merchant || null);
        }
        const total = db.prepare('SELECT COALESCE(SUM(amount),0) AS total FROM expense_lines WHERE report_id = ?').get(id);
        db.prepare('UPDATE expense_reports SET total_amount = ? WHERE id = ?').run(total.total, id);
      }
    });
    tx();
  }
  const user = session.user;
  db.prepare(`UPDATE expense_reports SET status = 'submitted' WHERE id = ?`).run(id);
  db.prepare(`INSERT INTO activity_logs (actor_id, report_id, event_type) VALUES (?, ?, 'report_resubmitted')`).run(user.id, id);
  return ok(res, { status: 'submitted' });
});

export default router;
