import express from 'express';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';
import { recordAudit } from '../services/userService.js';

const router = express.Router();

const EXPORT_DIR = process.env.EXPORT_STORAGE_DIR
  ? path.resolve(process.cwd(), process.env.EXPORT_STORAGE_DIR)
  : path.resolve(process.cwd(), 'storage/exports');

if (!fs.existsSync(EXPORT_DIR)) {
  fs.mkdirSync(EXPORT_DIR, { recursive: true });
}

function csvEscape(value) {
  if (value === null || value === undefined) return '';
  const s = String(value);
  if (s.includes('"') || s.includes(',') || s.includes('\n')) {
    return `"${s.replace(/"/g, '""')}"`;
  }
  return s;
}

function buildCsv(rows) {
  const headers = [
    'report_code',
    'title',
    'employee_username',
    'department_code',
    'cost_center',
    'total_amount',
    'currency',
    'status',
    'submitted_at',
    'manager_decision_at',
    'finance_decision_at',
    'reimbursement_at',
    'batch_id'
  ];
  const lines = [headers.join(',')];
  for (const r of rows) {
    lines.push([
      r.report_code,
      r.title,
      r.employee_username,
      r.department_code,
      r.cost_center,
      r.total_amount,
      r.currency,
      r.status,
      r.submitted_at,
      r.manager_decision_at,
      r.finance_decision_at,
      r.reimbursement_at,
      r.batch_id || ''
    ].map(csvEscape).join(','));
  }
  return lines.join('\n');
}

router.get('/reimbursement_export', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const exports = db.prepare(`
    SELECT e.*, u.username AS finance_username, sf.filename AS file_name
    FROM reimbursement_exports e
    JOIN users u ON u.id = e.finance_id
    LEFT JOIN stored_files sf ON sf.id = e.file_id
    ORDER BY e.created_at DESC LIMIT 50
  `).all();
  return ok(res, { exports });
});

router.post('/reimbursement_export', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const statuses = Array.isArray(req.body?.statuses) && req.body.statuses.length
    ? req.body.statuses
    : ['finance_approved', 'reimbursed', 'paid'];
  const departmentCode = req.body?.departmentCode || null;
  const placeholders = statuses.map(() => '?').join(',');
  const params = [...statuses];
  let sql = `
    SELECT r.report_code, r.title, r.total_amount, r.currency, r.status,
           r.submitted_at, r.manager_decision_at, r.finance_decision_at, r.reimbursement_at,
           u.username AS employee_username, d.code AS department_code, r.cost_center,
           (SELECT fr.batch_id FROM finance_reviews fr WHERE fr.report_id = r.id AND fr.decision IN ('approved','paid') ORDER BY fr.created_at DESC LIMIT 1) AS batch_id
    FROM expense_reports r
    JOIN users u ON u.id = r.employee_id
    LEFT JOIN departments d ON d.id = r.department_id
    WHERE r.status IN (${placeholders})
  `;
  if (departmentCode) {
    sql += ' AND d.code = ?';
    params.push(departmentCode);
  }
  sql += ' ORDER BY r.finance_decision_at ASC';
  const rows = db.prepare(sql).all(...params);

  const totals = rows.reduce((acc, r) => acc + Number(r.total_amount || 0), 0);
  const batchId = `BATCH-${Date.now().toString(36).toUpperCase()}`;
  const csv = buildCsv(rows);
  const storedName = `${batchId}.csv`;
  const fullPath = path.join(EXPORT_DIR, storedName);
  fs.writeFileSync(fullPath, csv, 'utf8');

  const fileInfo = db.prepare(`
    INSERT INTO stored_files (owner_id, filename, stored_name, mime_type, size_bytes, storage_path, context)
    VALUES (?, ?, ?, 'text/csv', ?, ?, 'reimbursement_export')
  `).run(session.user.id, `${batchId}.csv`, storedName, Buffer.byteLength(csv, 'utf8'), fullPath);

  const exportInfo = db.prepare(`
    INSERT INTO reimbursement_exports (batch_id, finance_id, file_id, filters, total_amount, record_count)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(batchId, session.user.id, fileInfo.lastInsertRowid, JSON.stringify({ statuses, departmentCode }), totals, rows.length);

  recordAudit(session.user.id, 'reimbursement_export', exportInfo.lastInsertRowid, 'create', { batchId, totals, recordCount: rows.length });
  return ok(res, {
    id: exportInfo.lastInsertRowid,
    batchId,
    fileId: fileInfo.lastInsertRowid,
    recordCount: rows.length,
    totalAmount: totals,
    downloadPath: `/api/exp/reimbursement_export/download/${batchId}`
  }, 201);
});

router.get('/reimbursement_export/download/:batchId', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'finance' && session.user.role !== 'admin') {
    return fail(res, 'Finance role required', 403, 'forbidden');
  }
  const exportRow = db.prepare(`
    SELECT e.*, sf.storage_path, sf.filename, sf.mime_type
    FROM reimbursement_exports e
    JOIN stored_files sf ON sf.id = e.file_id
    WHERE e.batch_id = ?
  `).get(req.params.batchId);
  if (!exportRow) return fail(res, 'Not found', 404, 'not_found');
  if (!fs.existsSync(exportRow.storage_path)) return fail(res, 'Stored export missing', 410, 'gone');
  res.setHeader('Content-Type', exportRow.mime_type || 'text/csv');
  res.setHeader('Content-Disposition', `attachment; filename="${exportRow.filename}"`);
  fs.createReadStream(exportRow.storage_path).pipe(res);
});

export default router;
