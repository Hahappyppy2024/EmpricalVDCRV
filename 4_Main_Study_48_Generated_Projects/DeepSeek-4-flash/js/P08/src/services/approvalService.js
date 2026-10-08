import { getDb } from '../db/database.js';
import { badRequest, forbidden, notFound, conflict } from '../lib/errors.js';
import { writeAudit, writeActivity } from './auditService.js';
import { canAccessReport, assertReportAccess, reportDetail } from './reportService.js';

const APPROVAL_DECISIONS = ['approved', 'rejected', 'changes_requested'];
const FINANCE_DECISIONS = ['approved', 'rejected', 'reimbursed'];

export function managerApprovalQueue(db, user) {
  return db
    .prepare(
      `SELECT r.id, r.report_no, r.title, r.status, r.total_amount, r.submitted_at, u.full_name AS employee_name,
              (SELECT COUNT(*) FROM comments c WHERE c.report_id = r.id) AS comment_count
       FROM expense_reports r JOIN users u ON u.id = r.employee_id
       WHERE r.submitted_to_id = ? AND r.status = 'submitted'
       ORDER BY r.submitted_at ASC`
    )
    .all(user.id);
}

export function financeReviewQueue(db) {
  return db
    .prepare(
      `SELECT r.id, r.report_no, r.title, r.status, r.total_amount, r.submitted_at, u.full_name AS employee_name,
              m.full_name AS approved_by
       FROM expense_reports r JOIN users u ON u.id = r.employee_id
       LEFT JOIN approvals a ON a.report_id = r.id AND a.id = (SELECT MAX(id) FROM approvals WHERE report_id = r.id)
       LEFT JOIN users m ON m.id = a.decided_by
       WHERE r.status IN ('approved', 'finance_approved')
       ORDER BY r.id ASC`
    )
    .all();
}

function assertManagerFor(db, user, report) {
  if (user.role !== 'manager' && user.role !== 'admin') throw forbidden('Only managers can approve reports');
  if (report.submitted_to_id && report.submitted_to_id !== user.id && user.role !== 'admin') {
    throw forbidden('This report was not routed to you for approval');
  }
}

export function managerDecision(db, user, { reportId, decision, comment }) {
  if (!APPROVAL_DECISIONS.includes(decision)) throw badRequest('Invalid decision; must be approved, rejected or changes_requested');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  if (report.status !== 'submitted') throw conflict('Only submitted reports can be decided', { current_status: report.status });
  assertManagerFor(db, user, report);

  const nextStatus = decision === 'approved' ? 'approved' : decision === 'rejected' ? 'rejected' : 'changes_requested';
  const tx = db.transaction(() => {
    db.prepare("UPDATE expense_reports SET status = ?, updated_at = datetime('now') WHERE id = ?").run(nextStatus, reportId);
    const info = db
      .prepare(
        `INSERT INTO approvals (report_id, decision, comment, decided_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))`
      )
      .run(reportId, decision, comment || null, user.id);
    writeAudit(db, { actorId: user.id, action: `approval.${decision}`, entityType: 'expense_report', entityId: reportId, details: { decision, comment } });
    writeActivity(db, { reportId, actorId: user.id, type: 'approval.decision', message: `Report ${report.report_no} marked as ${decision}` });
    return info.lastInsertRowid;
  });
  const approvalId = tx();
  return { approval_id: approvalId, report: reportDetail(db, reportId) };
}

export function updateApproval(db, user, approvalId, { decision, comment }) {
  const approval = db.prepare('SELECT * FROM approvals WHERE id = ?').get(approvalId);
  if (!approval) throw notFound('Approval record not found');
  if (approval.decided_by !== user.id && user.role !== 'admin') throw forbidden('You can only amend your own decisions');
  const fields = [];
  const params = [];
  if (comment !== undefined) {
    fields.push('comment = ?');
    params.push(comment || null);
  }
  if (decision !== undefined) {
    if (!APPROVAL_DECISIONS.includes(decision)) throw badRequest('Invalid decision');
    fields.push('decision = ?');
    params.push(decision);
  }
  if (fields.length === 0) throw badRequest('Nothing to update');
  fields.push('updated_at = datetime(\'now\')');
  params.push(approvalId);
  db.prepare(`UPDATE approvals SET ${fields.join(', ')} WHERE id = ?`).run(...params);
  writeAudit(db, { actorId: user.id, action: 'approval.updated', entityType: 'approval', entityId: approvalId });
  return db.prepare('SELECT * FROM approvals WHERE id = ?').get(approvalId);
}

export function financeDecision(db, user, { reportId, decision, comment }) {
  if (user.role !== 'finance' && user.role !== 'admin') throw forbidden('Only finance can review reports');
  if (!FINANCE_DECISIONS.includes(decision)) throw badRequest('Invalid decision; must be approved, rejected or reimbursed');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');

  let fromStatus;
  if (report.status === 'approved' && (decision === 'approved' || decision === 'rejected')) fromStatus = 'approved';
  else if (report.status === 'finance_approved' && decision === 'reimbursed') fromStatus = 'finance_approved';
  else throw conflict('Invalid state transition for this decision', { current_status: report.status });

  const nextStatus = decision === 'approved' ? 'finance_approved' : decision === 'rejected' ? 'finance_rejected' : 'reimbursed';
  const tx = db.transaction(() => {
    db.prepare("UPDATE expense_reports SET status = ?, updated_at = datetime('now') WHERE id = ?").run(nextStatus, reportId);
    const info = db
      .prepare(
        `INSERT INTO finance_reviews (report_id, decision, comment, decided_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))`
      )
      .run(reportId, decision, comment || null, user.id);
    writeAudit(db, { actorId: user.id, action: `finance.${decision}`, entityType: 'expense_report', entityId: reportId, details: { decision, comment } });
    writeActivity(db, { reportId, actorId: user.id, type: 'finance.review', message: `Report ${report.report_no} marked as ${decision}` });
    return info.lastInsertRowid;
  });
  const reviewId = tx();
  return { review_id: reviewId, report: reportDetail(db, reportId) };
}

export function updateFinanceReview(db, user, reviewId, { decision, comment }) {
  const review = db.prepare('SELECT * FROM finance_reviews WHERE id = ?').get(reviewId);
  if (!review) throw notFound('Finance review record not found');
  if (review.decided_by !== user.id && user.role !== 'admin') throw forbidden('You can only amend your own reviews');
  const fields = [];
  const params = [];
  if (comment !== undefined) {
    fields.push('comment = ?');
    params.push(comment || null);
  }
  if (decision !== undefined) {
    if (!FINANCE_DECISIONS.includes(decision)) throw badRequest('Invalid decision');
    fields.push('decision = ?');
    params.push(decision);
  }
  if (fields.length === 0) throw badRequest('Nothing to update');
  fields.push('updated_at = datetime(\'now\')');
  params.push(reviewId);
  db.prepare(`UPDATE finance_reviews SET ${fields.join(', ')} WHERE id = ?`).run(...params);
  writeAudit(db, { actorId: user.id, action: 'finance.updated', entityType: 'finance_review', entityId: reviewId });
  return db.prepare('SELECT * FROM finance_reviews WHERE id = ?').get(reviewId);
}

export function addComment(db, user, { reportId, body }) {
  if (!body || !String(body).trim()) throw badRequest('Comment text is required');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  assertReportAccess(db, user, report);
  const info = db
    .prepare(
      `INSERT INTO comments (report_id, author_id, body, created_at, updated_at)
       VALUES (?, ?, ?, datetime('now'), datetime('now'))`
    )
    .run(reportId, user.id, String(body).trim());
  writeActivity(db, { reportId, actorId: user.id, type: 'comment.created', message: `${user.full_name} commented on report ${report.report_no}` });
  writeAudit(db, { actorId: user.id, action: 'comment.created', entityType: 'comment', entityId: info.lastInsertRowid });
  const comment = db
    .prepare(
      `SELECT cm.id, cm.report_id, cm.body, cm.created_at, u.id AS author_id, u.full_name AS author_name
       FROM comments cm JOIN users u ON u.id = cm.author_id WHERE cm.id = ?`
    )
    .get(info.lastInsertRowid);
  return { comment, report_no: report.report_no };
}

export function updateComment(db, user, commentId, { body }) {
  const comment = db.prepare('SELECT * FROM comments WHERE id = ?').get(commentId);
  if (!comment) throw notFound('Comment not found');
  if (comment.author_id !== user.id && user.role !== 'admin') throw forbidden('You can only edit your own comments');
  if (!body || !String(body).trim()) throw badRequest('Comment text is required');
  db.prepare("UPDATE comments SET body = ?, updated_at = datetime('now') WHERE id = ?").run(String(body).trim(), commentId);
  writeAudit(db, { actorId: user.id, action: 'comment.updated', entityType: 'comment', entityId: commentId });
  return db
    .prepare(
      `SELECT cm.id, cm.report_id, cm.body, cm.created_at, u.id AS author_id, u.full_name AS author_name
       FROM comments cm JOIN users u ON u.id = cm.author_id WHERE cm.id = ?`
    )
    .get(commentId);
}

export function listComments(db, user, reportId) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  assertReportAccess(db, user, report);
  return db
    .prepare(
      `SELECT cm.id, cm.body, cm.created_at, u.full_name AS author_name, u.username
       FROM comments cm JOIN users u ON u.id = cm.author_id
       WHERE cm.report_id = ? ORDER BY cm.id`
    )
    .all(reportId);
}

export function listActivity(db, user, reportId) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  assertReportAccess(db, user, report);
  return db
    .prepare(
      `SELECT a.id, a.type, a.message, a.created_at, u.full_name AS actor_name
       FROM activity_events a LEFT JOIN users u ON u.id = a.actor_id
       WHERE a.report_id = ? ORDER BY a.id`
    )
    .all(reportId);
}

export function listVisibleEmployees(db, user) {
  if (user.role === 'admin' || user.role === 'finance') {
    return db
      .prepare(
        `SELECT u.id, u.username, u.full_name, u.email, u.role, u.department_id, u.manager_id,
                d.name AS department_name,
                (SELECT COUNT(*) FROM expense_reports r WHERE r.employee_id = u.id) AS report_count,
                (SELECT COALESCE(SUM(r.total_amount),0) FROM expense_reports r WHERE r.employee_id = u.id) AS total_amount
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.active = 1 ORDER BY u.id`
      )
      .all();
  }
  if (user.role === 'manager') {
    return db
      .prepare(
        `SELECT u.id, u.username, u.full_name, u.email, u.role, u.department_id, u.manager_id,
                d.name AS department_name,
                (SELECT COUNT(*) FROM expense_reports r WHERE r.employee_id = u.id) AS report_count,
                (SELECT COALESCE(SUM(r.total_amount),0) FROM expense_reports r WHERE r.employee_id = u.id) AS total_amount
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ? OR (u.manager_id = ? AND u.active = 1) ORDER BY u.id`
      )
      .all(user.id, user.id);
  }
  return db
    .prepare(
      `SELECT id, username, full_name, email, role, department_id, manager_id,
              (SELECT COUNT(*) FROM expense_reports r WHERE r.employee_id = u.id) AS report_count,
              (SELECT COALESCE(SUM(r.total_amount),0) FROM expense_reports r WHERE r.employee_id = u.id) AS total_amount
       FROM users u WHERE u.id = ?`
    )
    .all(user.id);
}

export function recordDataAccess(db, user, { employeeId, reportId, action = 'view', note }) {
  const subject = db.prepare('SELECT * FROM users WHERE id = ? AND active = 1').get(employeeId);
  if (!subject) throw notFound('Employee not found');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  if (report.employee_id !== subject.id) throw badRequest('The report does not belong to that employee');
  assertReportAccess(db, user, report);

  const existing = db.prepare('SELECT * FROM employee_data_accesses WHERE viewer_id = ? AND report_id = ?').get(user.id, reportId);
  if (existing) {
    db.prepare("UPDATE employee_data_accesses SET note = COALESCE(?, note), updated_at = datetime('now') WHERE id = ?").run(note || null, existing.id);
    writeAudit(db, { actorId: user.id, action: 'data_access.viewed', entityType: 'expense_report', entityId: reportId });
    return { access: { ...existing, note: note !== undefined ? note : existing.note }, detail: reportDetail(db, reportId) };
  }
  const info = db
    .prepare(
      `INSERT INTO employee_data_accesses (viewer_id, subject_id, report_id, action, note, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))`
    )
    .run(user.id, subject.id, reportId, action, note || null);
  writeAudit(db, { actorId: user.id, action: 'data_access.recorded', entityType: 'employee_data_access', entityId: info.lastInsertRowid, details: { subject_id: subject.id, report_id: reportId } });
  return { access: db.prepare('SELECT * FROM employee_data_accesses WHERE id = ?').get(info.lastInsertRowid), detail: reportDetail(db, reportId) };
}

export function updateDataAccess(db, user, accessId, { note }) {
  const access = db.prepare('SELECT * FROM employee_data_accesses WHERE id = ?').get(accessId);
  if (!access) throw notFound('Data access record not found');
  if (access.viewer_id !== user.id && user.role !== 'admin') throw forbidden('You can only update your own access records');
  db.prepare("UPDATE employee_data_accesses SET note = ?, updated_at = datetime('now') WHERE id = ?").run(note || null, accessId);
  writeAudit(db, { actorId: user.id, action: 'data_access.updated', entityType: 'employee_data_access', entityId: accessId });
  return db.prepare('SELECT * FROM employee_data_accesses WHERE id = ?').get(accessId);
}

export function listDataAccesses(db, user) {
  const rows = db
    .prepare(
      `SELECT a.id, a.viewer_id, a.subject_id, a.report_id, a.action, a.note, a.created_at,
              s.full_name AS subject_name, r.report_no, r.title, r.status
       FROM employee_data_accesses a
       JOIN users s ON s.id = a.subject_id
       JOIN expense_reports r ON r.id = a.report_id
       WHERE a.viewer_id = ?
       ORDER BY a.id DESC`
    )
    .all(user.id);
  return rows;
}

export { canAccessReport };
