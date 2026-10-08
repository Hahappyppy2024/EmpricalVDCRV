import { getDb } from '../db/database.js';
import { badRequest, notFound, conflict } from '../lib/errors.js';
import { writeAudit } from './auditService.js';
import { reportDetail, assertReportAccess } from './reportService.js';
import { evaluatePolicy } from './policyService.js';

export function frontendState(db, user) {
  const myDrafts = db
    .prepare(
      `SELECT id, report_no, title, total_amount, status FROM expense_reports
       WHERE employee_id = ? AND status = 'draft' ORDER BY id DESC LIMIT 10`
    )
    .all(user.id);
  const warnings = myDrafts.map((r) => ({
    report_id: r.id,
    report_no: r.report_no,
    title: r.title,
    warnings: evaluatePolicy(db, r),
  }));
  const errors = db
    .prepare(
      `SELECT e.id, e.code, e.message, e.path, e.payload, e.acknowledged, e.created_at
       FROM client_error_states e
       WHERE e.user_id = ? OR e.user_id IS NULL OR (? = 'admin')
       ORDER BY e.id DESC LIMIT 25`
    )
    .all(user.id, user.role);
  const pendingCount = db.prepare("SELECT COUNT(*) AS n FROM expense_reports WHERE employee_id = ? AND status IN ('submitted','approved','finance_approved')").get(user.id).n;
  return {
    user: { id: user.id, username: user.username, full_name: user.full_name, role: user.role },
    draft_warnings: warnings,
    pending_count: pendingCount,
    recent_client_errors: errors,
  };
}

export function reportClientError(db, user, { code, message, path, payload }) {
  if (!code || !message) throw badRequest('code and message are required');
  const info = db
    .prepare(
      `INSERT INTO client_error_states (user_id, code, message, path, payload, acknowledged, created_at)
       VALUES (?, ?, ?, ?, ?, 0, datetime('now'))`
    )
    .run(user.id, String(code), String(message), path || null, payload ? JSON.stringify(payload) : null);
  writeAudit(db, { actorId: user.id, action: 'frontend.error_reported', entityType: 'client_error_state', entityId: info.lastInsertRowid, details: { code, path } });
  return db.prepare('SELECT * FROM client_error_states WHERE id = ?').get(info.lastInsertRowid);
}

export function simulateConflict(db, user, { reportId, expectedStatus }) {
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) throw notFound('Expense report not found');
  assertReportAccess(db, user, report);
  if (expectedStatus !== undefined && report.status !== expectedStatus) {
    const err = conflict('The report state does not match the expected status', {
      report_id: reportId,
      expected_status: expectedStatus,
      current_status: report.status,
    });
    reportClientError(db, user, {
      code: 'STATE_CONFLICT',
      message: `Expected status ${expectedStatus} but the report is ${report.status}`,
      path: '/api/exp/frontend_api_integration_and_errors',
      payload: { report_id: reportId, expected_status: expectedStatus, current_status: report.status },
    });
    throw err;
  }
  return {
    ok: true,
    message: 'No state conflict detected',
    report: reportDetail(db, reportId),
    warnings: evaluatePolicy(db, report),
  };
}

export function acknowledgeClientError(db, user, errorId, { acknowledged }) {
  const record = db.prepare('SELECT * FROM client_error_states WHERE id = ?').get(errorId);
  if (!record) throw notFound('Client error state not found');
  if (record.user_id && record.user_id !== user.id && user.role !== 'admin') {
    throw badRequest('You can only acknowledge your own error states');
  }
  db.prepare('UPDATE client_error_states SET acknowledged = ? WHERE id = ?').run(acknowledged ? 1 : 0, errorId);
  writeAudit(db, { actorId: user.id, action: 'frontend.error_acknowledged', entityType: 'client_error_state', entityId: errorId });
  return db.prepare('SELECT * FROM client_error_states WHERE id = ?').get(errorId);
}
