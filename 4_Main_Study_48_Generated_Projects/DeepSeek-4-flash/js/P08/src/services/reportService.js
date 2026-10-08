import { getDb } from '../db/database.js';
import { badRequest, forbidden, notFound, conflict } from '../lib/errors.js';
import { writeAudit, writeActivity } from './auditService.js';
import { policyWarningsForReport, evaluatePolicy } from './policyService.js';

export function nextReportNo(db) {
  const year = new Date().getFullYear();
  const row = db
    .prepare(`SELECT MAX(CAST(SUBSTR(report_no, 10) AS INTEGER)) AS max FROM expense_reports WHERE report_no LIKE ?`)
    .get(`EXP-${year}-%`);
  const next = (row.max || 0) + 1;
  return `EXP-${year}-${String(next).padStart(4, '0')}`;
}

export function canAccessReport(db, user, report) {
  if (user.role === 'admin' || user.role === 'finance') return true;
  if (report.employee_id === user.id) return true;
  if (user.role === 'manager') {
    const direct = db.prepare('SELECT id FROM users WHERE manager_id = ?').all(user.id).map((r) => r.id);
    if (direct.includes(report.employee_id)) return true;
  }
  return false;
}

export function assertReportAccess(db, user, report) {
  if (!canAccessReport(db, user, report)) throw forbidden('You cannot access this report');
}

export function reportDetail(db, reportId) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  const employee = db.prepare('SELECT * FROM users WHERE id = ?').get(report.employee_id);
  const lines = db
    .prepare(
      `SELECT l.id, l.category_id, c.name AS category_name, l.expense_date, l.description, l.merchant, l.amount, l.receipt_id
       FROM expense_lines l LEFT JOIN categories c ON c.id = l.category_id
       WHERE l.report_id = ? ORDER BY l.id`
    )
    .all(reportId);
  const receipts = db
    .prepare(
      `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, f.stored_name
       FROM receipts r JOIN stored_files f ON f.id = r.file_id
       WHERE r.report_id = ? ORDER BY r.id`
    )
    .all(reportId);
  const approvals = db
    .prepare(
      `SELECT a.id, a.decision, a.comment, a.created_at, u.full_name AS decided_by_name
       FROM approvals a JOIN users u ON u.id = a.decided_by
       WHERE a.report_id = ? ORDER BY a.id`
    )
    .all(reportId);
  const reviews = db
    .prepare(
      `SELECT f.id, f.decision, f.comment, f.created_at, u.full_name AS decided_by_name
       FROM finance_reviews f JOIN users u ON u.id = f.decided_by
       WHERE f.report_id = ? ORDER BY f.id`
    )
    .all(reportId);
  const comments = db
    .prepare(
      `SELECT cm.id, cm.body, cm.created_at, u.full_name AS author_name, u.username
       FROM comments cm JOIN users u ON u.id = cm.author_id
       WHERE cm.report_id = ? ORDER BY cm.id`
    )
    .all(reportId);
  const activity = db
    .prepare(
      `SELECT a.id, a.type, a.message, a.created_at, u.full_name AS actor_name
       FROM activity_events a LEFT JOIN users u ON u.id = a.actor_id
       WHERE a.report_id = ? ORDER BY a.id`
    )
    .all(reportId);
  return {
    ...report,
    employee: employee ? { id: employee.id, full_name: employee.full_name, username: employee.username } : null,
    lines,
    receipts,
    approvals,
    reviews,
    comments,
    activity,
    policy_warnings: policyWarningsForReport(db, report),
  };
}

export function listVisibleReports(db, user, { status, categoryId, limit = 100, offset = 0 } = {}) {
  const where = [];
  const params = [];
  if (user.role === 'employee') {
    where.push('r.employee_id = ?');
    params.push(user.id);
  } else if (user.role === 'manager') {
    where.push('(r.employee_id = ? OR r.employee_id IN (SELECT id FROM users WHERE manager_id = ?))');
    params.push(user.id, user.id);
  }
  if (status) {
    where.push('r.status = ?');
    params.push(status);
  }
  const sql = `
    SELECT r.id, r.report_no, r.title, r.status, r.total_amount, r.created_at, r.submitted_at,
           u.full_name AS employee_name
    FROM expense_reports r JOIN users u ON u.id = r.employee_id
    ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
    ORDER BY r.id DESC LIMIT ? OFFSET ?
  `;
  params.push(Math.min(Math.max(Number(limit) || 100, 1), 500), Math.max(Number(offset) || 0, 0));
  return db.prepare(sql).all(...params);
}

export function createReport(db, user, { title, purpose, departmentId, costCenterId, lines }) {
  if (!title || !String(title).trim()) throw badRequest('title is required');
  if (!Array.isArray(lines) || lines.length === 0) throw badRequest('At least one expense line is required');
  const cleanLines = lines.map((l, i) => {
    const amount = Number(l.amount);
    if (!l.category_id) throw badRequest(`Line ${i + 1}: category_id is required`);
    if (!l.expense_date) throw badRequest(`Line ${i + 1}: expense_date is required`);
    if (!l.description || !String(l.description).trim()) throw badRequest(`Line ${i + 1}: description is required`);
    if (!Number.isFinite(amount) || amount <= 0) throw badRequest(`Line ${i + 1}: amount must be a positive number`);
    return { ...l, amount, description: String(l.description).trim() };
  });

  const deptId = departmentId || user.department_id;
  const ccId = costCenterId || user.cost_center_id;
  const cat = db.prepare('SELECT id FROM categories WHERE id = ?').get(cleanLines[0].category_id);
  if (!cat) throw badRequest('Invalid category_id');

  const reportNo = nextReportNo(db);
  const tx = db.transaction(() => {
    const info = db
      .prepare(
        `INSERT INTO expense_reports (report_no, employee_id, title, purpose, department_id, cost_center_id, status, total_amount, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 'draft', 0, datetime('now'), datetime('now'))`
      )
      .run(reportNo, user.id, String(title).trim(), purpose || null, deptId || null, ccId || null);
    const reportId = info.lastInsertRowid;
    let total = 0;
    const insLine = db.prepare(
      `INSERT INTO expense_lines (report_id, category_id, expense_date, description, merchant, amount, created_at)
       VALUES (?, ?, ?, ?, ?, ?, datetime('now'))`
    );
    for (const l of cleanLines) {
      insLine.run(reportId, l.category_id, l.expense_date, l.description, l.merchant || null, l.amount);
      total += l.amount;
    }
    db.prepare('UPDATE expense_reports SET total_amount = ?, updated_at = datetime(\'now\') WHERE id = ?').run(total, reportId);
    writeAudit(db, { actorId: user.id, action: 'report.created', entityType: 'expense_report', entityId: reportId, details: { report_no: reportNo } });
    writeActivity(db, { reportId, actorId: user.id, type: 'report.created', message: `Report ${reportNo} created` });
    return reportId;
  });
  const reportId = tx();
  return reportDetail(db, reportId);
}

export function updateDraftReport(db, user, reportId, { title, purpose, departmentId, costCenterId, lines }) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  if (report.employee_id !== user.id) throw forbidden('You can only update your own reports');
  if (report.status !== 'draft') throw conflict('Only draft reports can be edited', { current_status: report.status });

  const tx = db.transaction(() => {
    if (title !== undefined) {
      if (!String(title).trim()) throw badRequest('title cannot be empty');
      db.prepare('UPDATE expense_reports SET title = ?, updated_at = datetime(\'now\') WHERE id = ?').run(String(title).trim(), reportId);
    }
    if (purpose !== undefined) db.prepare('UPDATE expense_reports SET purpose = ?, updated_at = datetime(\'now\') WHERE id = ?').run(purpose || null, reportId);
    if (departmentId !== undefined) db.prepare('UPDATE expense_reports SET department_id = ?, updated_at = datetime(\'now\') WHERE id = ?').run(departmentId || null, reportId);
    if (costCenterId !== undefined) db.prepare('UPDATE expense_reports SET cost_center_id = ?, updated_at = datetime(\'now\') WHERE id = ?').run(costCenterId || null, reportId);
    if (lines !== undefined) {
      if (!Array.isArray(lines) || lines.length === 0) throw badRequest('At least one expense line is required');
      const cleanLines = lines.map((l, i) => {
        const amount = Number(l.amount);
        if (!l.category_id) throw badRequest(`Line ${i + 1}: category_id is required`);
        if (!l.expense_date) throw badRequest(`Line ${i + 1}: expense_date is required`);
        if (!l.description || !String(l.description).trim()) throw badRequest(`Line ${i + 1}: description is required`);
        if (!Number.isFinite(amount) || amount <= 0) throw badRequest(`Line ${i + 1}: amount must be a positive number`);
        return { ...l, amount, description: String(l.description).trim() };
      });
      db.prepare('DELETE FROM expense_lines WHERE report_id = ?').run(reportId);
      const insLine = db.prepare(
        `INSERT INTO expense_lines (report_id, category_id, expense_date, description, merchant, amount, created_at)
         VALUES (?, ?, ?, ?, ?, ?, datetime('now'))`
      );
      let total = 0;
      for (const l of cleanLines) {
        insLine.run(reportId, l.category_id, l.expense_date, l.description, l.merchant || null, l.amount);
        total += l.amount;
      }
      db.prepare('UPDATE expense_reports SET total_amount = ?, updated_at = datetime(\'now\') WHERE id = ?').run(total, reportId);
    }
    writeAudit(db, { actorId: user.id, action: 'report.updated', entityType: 'expense_report', entityId: reportId });
    writeActivity(db, { reportId, actorId: user.id, type: 'report.updated', message: `Report ${report.report_no} updated` });
  });
  tx();
  return reportDetail(db, reportId);
}

export function submitReport(db, user, { reportId }) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  if (report.employee_id !== user.id) throw forbidden('You can only submit your own reports');
  if (report.status !== 'draft') throw conflict('Only draft reports can be submitted', { current_status: report.status });
  if (!report.total_amount || report.total_amount <= 0) throw badRequest('A report must have a positive total to be submitted');
  const submittedTo = findManager(db, report.employee_id) || db.prepare('SELECT id, full_name, username FROM users WHERE role = ? AND active = 1 ORDER BY id LIMIT 1').get('manager');
  if (!submittedTo) throw badRequest('No approving manager is available for this employee');
  db.prepare(
    `UPDATE expense_reports SET status = 'submitted', submitted_at = datetime('now'), submitted_to_id = ?, updated_at = datetime('now') WHERE id = ?`
  ).run(submittedTo.id, reportId);
  const warnings = evaluatePolicy(db, report);
  writeAudit(db, { actorId: user.id, action: 'report.submitted', entityType: 'expense_report', entityId: reportId, details: { warnings } });
  writeActivity(db, { reportId, actorId: user.id, type: 'report.submitted', message: `Report ${report.report_no} submitted to ${submittedTo.full_name}` });
  return { ...reportDetail(db, reportId), submitted_to: submittedTo, policy_warnings: warnings };
}

function findManager(db, employeeId) {
  const emp = db.prepare('SELECT manager_id FROM users WHERE id = ?').get(employeeId);
  if (!emp || !emp.manager_id) return null;
  const mgr = db.prepare('SELECT id, full_name, username FROM users WHERE id = ? AND active = 1').get(emp.manager_id);
  return mgr && (mgr.role === 'manager' || mgr.role === 'admin') ? mgr : null;
}

export function recallReport(db, user, { reportId }) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  if (report.employee_id !== user.id) throw forbidden('You can only recall your own reports');
  if (report.status !== 'submitted') throw conflict('Only submitted reports can be recalled', { current_status: report.status });
  db.prepare(
    `UPDATE expense_reports SET status = 'draft', submitted_to_id = NULL, submitted_at = NULL, updated_at = datetime('now') WHERE id = ?`
  ).run(reportId);
  writeAudit(db, { actorId: user.id, action: 'report.recalled', entityType: 'expense_report', entityId: reportId });
  writeActivity(db, { reportId, actorId: user.id, type: 'report.recalled', message: `Report ${report.report_no} recalled to draft` });
  return reportDetail(db, reportId);
}
