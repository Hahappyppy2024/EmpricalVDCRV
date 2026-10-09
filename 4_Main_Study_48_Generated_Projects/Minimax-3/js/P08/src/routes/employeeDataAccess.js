import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

function visibleUserIdsForSession(session) {
  const user = session.user;
  if (user.role === 'admin' || user.role === 'finance') {
    return db.prepare('SELECT id FROM users').all().map(r => r.id);
  }
  if (user.role === 'manager') {
    const ids = db.prepare('SELECT id FROM users WHERE manager_id = ? OR id = ?').all(user.id, user.id).map(r => r.id);
    return ids;
  }
  return [user.id];
}

router.get('/employee_data_access', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const ids = visibleUserIdsForSession(session);
  if (!ids.length) return ok(res, { employees: [], departments: [] });
  const placeholders = ids.map(() => '?').join(',');
  const employees = db.prepare(`
    SELECT u.id, u.username, u.email, u.full_name, u.role, u.department_id,
           d.name AS department_name, d.cost_center, mgr.username AS manager_username
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN users mgr ON mgr.id = u.manager_id
    WHERE u.id IN (${placeholders})
    ORDER BY u.role, u.username
  `).all(...ids);
  const departments = db.prepare('SELECT id, name, code, cost_center FROM departments ORDER BY name').all();
  return ok(res, { employees, departments });
});

router.get('/employee_data_access/:id', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const visibleIds = visibleUserIdsForSession(session);
  if (!visibleIds.includes(id)) return fail(res, 'Forbidden', 403, 'forbidden');
  const user = db.prepare(`
    SELECT u.*, d.name AS department_name, d.cost_center, mgr.username AS manager_username
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN users mgr ON mgr.id = u.manager_id
    WHERE u.id = ?
  `).get(id);
  if (!user) return fail(res, 'Not found', 404, 'not_found');
  const reports = db.prepare('SELECT id, report_code, title, status, total_amount, currency, created_at FROM expense_reports WHERE employee_id = ? ORDER BY created_at DESC').all(id);
  return ok(res, { user: { ...user, password_hash: undefined }, reports });
});

router.post('/employee_data_access', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const { reportId } = req.body || {};
  if (!reportId) return fail(res, 'reportId required', 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(reportId));
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  const visibleIds = visibleUserIdsForSession(session);
  if (!visibleIds.includes(report.employee_id)) return fail(res, 'Forbidden', 403, 'forbidden');
  const lines = db.prepare(`
    SELECT el.id, el.description, el.amount, el.currency, el.expense_date, el.merchant,
           c.name AS category_name, c.code AS category_code, sf.id AS file_id, sf.filename AS receipt_filename
    FROM expense_lines el
    LEFT JOIN categories c ON c.id = el.category_id
    LEFT JOIN stored_files sf ON sf.id = el.receipt_id
    WHERE el.report_id = ? ORDER BY el.id ASC
  `).all(report.id);
  const employee = db.prepare('SELECT id, username, full_name, role FROM users WHERE id = ?').get(report.employee_id);
  return ok(res, { report, lines, employee });
});

export default router;
