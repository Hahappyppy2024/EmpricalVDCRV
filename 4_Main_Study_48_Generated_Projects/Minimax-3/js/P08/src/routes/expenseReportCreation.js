import express from 'express';
import { db } from '../db/index.js';
import { ok, fail, AppError } from '../middleware/http.js';

const router = express.Router();

const ALLOWED_CATEGORIES = new Set(['TRV', 'MLS', 'SUP', 'SFT', 'TRN']);

function newReportCode() {
  const ts = Date.now().toString(36).toUpperCase();
  const rnd = Math.floor(Math.random() * 46656).toString(36).toUpperCase().padStart(3, '0');
  return `EXP-${ts}-${rnd}`;
}

function recalcTotal(reportId) {
  const row = db.prepare('SELECT COALESCE(SUM(amount),0) AS total FROM expense_lines WHERE report_id = ?').get(reportId);
  db.prepare('UPDATE expense_reports SET total_amount = ?, updated_at = datetime(\'now\') WHERE id = ?').run(row.total, reportId);
  return row.total;
}

router.get('/expense_report_creation', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const user = session.user;

  let rows;
  if (user.role === 'employee') {
    rows = db.prepare(`
      SELECT r.*, (SELECT COUNT(*) FROM expense_lines WHERE report_id = r.id) AS line_count
      FROM expense_reports r WHERE r.employee_id = ? ORDER BY r.created_at DESC
    `).all(user.id);
  } else if (user.role === 'manager') {
    rows = db.prepare(`
      SELECT r.*, (SELECT COUNT(*) FROM expense_lines WHERE report_id = r.id) AS line_count
      FROM expense_reports r
      JOIN users u ON u.id = r.employee_id
      WHERE u.manager_id = ? OR r.employee_id = ?
      ORDER BY r.created_at DESC
    `).all(user.id, user.id);
  } else {
    rows = db.prepare(`
      SELECT r.*, (SELECT COUNT(*) FROM expense_lines WHERE report_id = r.id) AS line_count
      FROM expense_reports r ORDER BY r.created_at DESC LIMIT 100
    `).all();
  }

  const cats = db.prepare('SELECT id, name, code, description FROM categories ORDER BY name').all();
  const categories = cats.map(c => ({ id: c.id, name: c.name, code: c.code, description: c.description }));
  return ok(res, { reports: rows, categories });
});

function ensureOwnershipOrStaff(session, report) {
  if (!report) throw new AppError('Report not found', 404, 'not_found');
  const user = session.user;
  if (user.role === 'admin' || user.role === 'finance') return;
  if (user.role === 'manager') {
    const owner = db.prepare('SELECT manager_id FROM users WHERE id = ?').get(report.employee_id);
    if (owner?.manager_id !== user.id && report.employee_id !== user.id) {
      throw new AppError('Not your report', 403, 'forbidden');
    }
    return;
  }
  if (report.employee_id !== user.id) {
    throw new AppError('Not your report', 403, 'forbidden');
  }
}

router.get('/expense_report_creation/:id', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(id);
  try { ensureOwnershipOrStaff(session, report); }
  catch (e) { return fail(res, e.message, e.status, e.code); }

  const lines = db.prepare(`
    SELECT el.*, c.name AS category_name, c.code AS category_code, sf.filename AS receipt_filename
    FROM expense_lines el
    LEFT JOIN categories c ON c.id = el.category_id
    LEFT JOIN stored_files sf ON sf.id = el.receipt_id
    WHERE el.report_id = ? ORDER BY el.id ASC
  `).all(id);
  return ok(res, { report, lines });
});

router.post('/expense_report_creation', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'employee' && session.user.role !== 'admin') {
    return fail(res, 'Only employees can create draft reports', 403, 'forbidden');
  }
  const { title, description, departmentId, costCenter } = req.body || {};
  if (!title || typeof title !== 'string' || title.length < 3) {
    return fail(res, 'title is required (min 3 chars)', 400, 'validation_error');
  }
  const code = newReportCode();
  const dept = departmentId ? db.prepare('SELECT * FROM departments WHERE id = ?').get(departmentId) : null;
  const cc = costCenter || dept?.cost_center || null;
  const employeeId = session.user.id;
  const info = db.prepare(`
    INSERT INTO expense_reports (report_code, employee_id, title, description, status, department_id, cost_center)
    VALUES (?, ?, ?, ?, 'draft', ?, ?)
  `).run(code, employeeId, title, description || null, dept?.id || null, cc);
  db.prepare(`
    INSERT INTO activity_logs (actor_id, report_id, event_type, details)
    VALUES (?, ?, 'report_created', ?)
  `).run(employeeId, info.lastInsertRowid, JSON.stringify({ code }));
  return ok(res, { id: info.lastInsertRowid, reportCode: code }, 201);
});

router.patch('/expense_report_creation/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(id);
  try { ensureOwnershipOrStaff(session, report); }
  catch (e) { return fail(res, e.message, e.status, e.code); }
  if (report.status !== 'draft') {
    return fail(res, `Cannot edit report in status ${report.status}`, 409, 'invalid_transition');
  }
  const { title, description, departmentId, costCenter, lines } = req.body || {};
  const tx = db.transaction(() => {
    const updates = [];
    const values = [];
    if (title) { updates.push('title = ?'); values.push(title); }
    if (description !== undefined) { updates.push('description = ?'); values.push(description); }
    if (departmentId !== undefined) { updates.push('department_id = ?'); values.push(departmentId || null); }
    if (costCenter !== undefined) { updates.push('cost_center = ?'); values.push(costCenter || null); }
    if (updates.length) {
      updates.push("updated_at = datetime('now')");
      values.push(id);
      db.prepare(`UPDATE expense_reports SET ${updates.join(', ')} WHERE id = ?`).run(...values);
    }
    if (Array.isArray(lines)) {
      db.prepare('DELETE FROM expense_lines WHERE report_id = ?').run(id);
      const insertLine = db.prepare(`
        INSERT INTO expense_lines (report_id, category_id, description, expense_date, amount, currency, merchant)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `);
      for (const l of lines) {
        if (!l || !l.description || !l.expenseDate || l.amount == null) continue;
        const cat = l.categoryCode ? db.prepare('SELECT id FROM categories WHERE code = ?').get(String(l.categoryCode)) : null;
        if (l.categoryCode && !cat) continue;
        if (cat && !ALLOWED_CATEGORIES.has(l.categoryCode)) continue;
        insertLine.run(id, cat?.id || null, String(l.description), String(l.expenseDate), Number(l.amount), l.currency || 'USD', l.merchant || null);
      }
    }
    recalcTotal(id);
  });
  tx();
  return ok(res, { updated: true });
});

export default router;
