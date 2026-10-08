import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { getDb } from '../db/database.js';
import { config } from '../config.js';
import { badRequest, forbidden, notFound } from '../lib/errors.js';
import { writeAudit, writeActivity } from './auditService.js';

function safeExt(name) {
  const m = /\.([a-z0-9]+)$/i.exec(name || '');
  return m ? `.${m[1].toLowerCase()}` : '';
}

export function storeFile(db, user, { originalName, mimeType, size, buffer, entityType, entityId }) {
  const storedName = `${crypto.randomUUID()}${safeExt(originalName)}`;
  const filePath = path.join(config.uploadDir, storedName);
  fs.writeFileSync(filePath, buffer);
  const info = db
    .prepare(
      `INSERT INTO stored_files (original_name, stored_name, mime_type, size, owner_id, entity_type, entity_id, path, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))`
    )
    .run(originalName || storedName, storedName, mimeType, size, user.id, entityType, entityId, filePath);
  return db.prepare('SELECT * FROM stored_files WHERE id = ?').get(info.lastInsertRowid);
}

export function listReceipts(db, user) {
  if (user.role === 'admin' || user.role === 'finance') {
    return db
      .prepare(
        `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, r.uploaded_by, r.created_at,
                u.full_name AS uploaded_by_name, rep.report_no
         FROM receipts r
         JOIN stored_files f ON f.id = r.file_id
         JOIN users u ON u.id = r.uploaded_by
         JOIN expense_reports rep ON rep.id = r.report_id
         ORDER BY r.id DESC`
      )
      .all();
  }
  if (user.role === 'manager') {
    return db
      .prepare(
        `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, r.uploaded_by, r.created_at,
                u.full_name AS uploaded_by_name, rep.report_no
         FROM receipts r
         JOIN stored_files f ON f.id = r.file_id
         JOIN users u ON u.id = r.uploaded_by
         JOIN expense_reports rep ON rep.id = r.report_id
         WHERE rep.employee_id = ? OR rep.employee_id IN (SELECT id FROM users WHERE manager_id = ?)
         ORDER BY r.id DESC`
      )
      .all(user.id, user.id);
  }
  return db
    .prepare(
      `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, r.uploaded_by, r.created_at,
              u.full_name AS uploaded_by_name, rep.report_no
       FROM receipts r
       JOIN stored_files f ON f.id = r.file_id
       JOIN users u ON u.id = r.uploaded_by
       JOIN expense_reports rep ON rep.id = r.report_id
       WHERE rep.employee_id = ?
       ORDER BY r.id DESC`
    )
    .all(user.id);
}

export function attachReceipt(db, user, { file, reportId, lineId }) {
  if (!file) throw badRequest('A receipt file is required');
  if (!config.allowedUploadMimeTypes.includes(file.mimetype)) {
    throw badRequest(`File type ${file.mimetype} is not allowed`);
  }
  if (file.size > config.maxUploadBytes) {
    throw badRequest(`File exceeds the ${config.maxUploadBytes} byte limit`);
  }

  let report = null;
  if (reportId) {
    report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
    if (!report) throw notFound('Expense report not found');
    if (report.employee_id !== user.id && user.role !== 'admin' && user.role !== 'finance') {
      throw forbidden('You can only attach receipts to your own reports');
    }
  }
  if (lineId) {
    const line = db.prepare('SELECT * FROM expense_lines WHERE id = ?').get(lineId);
    if (!line) throw notFound('Expense line not found');
    if (report && line.report_id !== report.id) throw badRequest('The expense line does not belong to the given report');
    report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(line.report_id);
    if (report.employee_id !== user.id && user.role !== 'admin' && user.role !== 'finance') {
      throw forbidden('You can only attach receipts to your own reports');
    }
  }
  if (!report) throw badRequest('report_id or line_id is required');

  const stored = storeFile(db, user, {
    originalName: file.originalname,
    mimeType: file.mimetype,
    size: file.size,
    buffer: file.buffer,
    entityType: 'receipt',
    entityId: report.id,
  });
  const info = db
    .prepare(
      `INSERT INTO receipts (report_id, line_id, file_id, uploaded_by, created_at)
       VALUES (?, ?, ?, ?, datetime('now'))`
    )
    .run(report.id, lineId || null, stored.id, user.id);
  if (lineId) db.prepare('UPDATE expense_lines SET receipt_id = ? WHERE id = ?').run(stored.id, lineId);
  writeAudit(db, { actorId: user.id, action: 'receipt.uploaded', entityType: 'stored_file', entityId: stored.id, details: { report_id: report.id, line_id: lineId || null } });
  writeActivity(db, { reportId: report.id, actorId: user.id, type: 'receipt.uploaded', message: `Receipt ${stored.original_name} attached to ${report.report_no}` });
  const receipt = db
    .prepare(
      `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, f.stored_name
       FROM receipts r JOIN stored_files f ON f.id = r.file_id WHERE r.id = ?`
    )
    .get(info.lastInsertRowid);
  return { receipt, report_no: report.report_no };
}

export function updateReceiptMetadata(db, user, receiptId, { originalName }) {
  const receipt = db.prepare('SELECT * FROM receipts WHERE id = ?').get(receiptId);
  if (!receipt) throw notFound('Receipt not found');
  const file = db.prepare('SELECT * FROM stored_files WHERE id = ?').get(receipt.file_id);
  if (file.owner_id !== user.id && user.role !== 'admin') throw forbidden('You can only edit your own receipts');
  if (originalName !== undefined) {
    if (!String(originalName).trim()) throw badRequest('original_name cannot be empty');
    db.prepare('UPDATE stored_files SET original_name = ? WHERE id = ?').run(String(originalName).trim(), file.id);
  }
  writeAudit(db, { actorId: user.id, action: 'receipt.updated', entityType: 'stored_file', entityId: file.id });
  return db
    .prepare(
      `SELECT r.id, r.report_id, r.line_id, r.file_id, f.original_name, f.mime_type, f.size, f.stored_name
       FROM receipts r JOIN stored_files f ON f.id = r.file_id WHERE r.id = ?`
    )
    .get(receiptId);
}

export function getFileRecord(db, id) {
  return db.prepare('SELECT * FROM stored_files WHERE id = ?').get(id);
}

export function canDownloadFile(db, user, file) {
  if (user.role === 'admin' || user.role === 'finance') return true;
  if (file.owner_id === user.id) return true;
  if (file.entity_type === 'receipt' && file.entity_id) {
    const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(file.entity_id);
    if (!report) return false;
    if (report.employee_id === user.id) return true;
    if (user.role === 'manager') {
      const direct = db.prepare('SELECT id FROM users WHERE manager_id = ?').all(user.id).map((r) => r.id);
      if (direct.includes(report.employee_id)) return true;
    }
  }
  return false;
}

export function buildCsvExport(db, user, { reportIds }) {
  let rows;
  if (reportIds && reportIds.length) {
    const placeholders = reportIds.map(() => '?').join(',');
    rows = db
      .prepare(
        `SELECT r.report_no, u.full_name AS employee, r.title, r.total_amount, r.status, r.submitted_at
         FROM expense_reports r JOIN users u ON u.id = r.employee_id
         WHERE r.id IN (${placeholders}) AND r.status IN ('finance_approved','reimbursed','approved')`
      )
      .all(...reportIds);
  } else {
    rows = db
      .prepare(
        `SELECT r.report_no, u.full_name AS employee, r.title, r.total_amount, r.status, r.submitted_at
         FROM expense_reports r JOIN users u ON u.id = r.employee_id
         WHERE r.status IN ('finance_approved','reimbursed','approved')
         ORDER BY r.id`
      )
      .all();
  }
  if (!rows.length) throw badRequest('No reports match the export filter');

  const esc = (v) => {
    const s = v === null || v === undefined ? '' : String(v);
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const header = ['report_no', 'employee', 'title', 'total_amount', 'status', 'submitted_at'];
  const lines = [header.map(esc).join(',')];
  for (const r of rows) lines.push([r.report_no, r.employee, r.title, r.total_amount, r.status, r.submitted_at].map(esc).join(','));
  const csv = lines.join('\n') + '\n';

  const batchNo = `EXP-BATCH-${new Date().getFullYear()}-${String(Math.floor(Date.now() / 1000)).slice(-6)}`;
  const stored = storeFile(db, user, {
    originalName: `reimbursement-export-${batchNo}.csv`,
    mimeType: 'text/csv',
    size: Buffer.byteLength(csv),
    buffer: Buffer.from(csv, 'utf8'),
    entityType: 'export',
    entityId: null,
  });
  const info = db
    .prepare(
      `INSERT INTO export_batches (batch_no, status, file_id, created_by, row_count, created_at)
       VALUES (?, 'generated', ?, ?, ?, datetime('now'))`
    )
    .run(batchNo, stored.id, user.id, rows.length);
  writeAudit(db, { actorId: user.id, action: 'export.generated', entityType: 'export_batch', entityId: info.lastInsertRowid, details: { batch_no: batchNo, row_count: rows.length } });
  return {
    batch: db.prepare('SELECT * FROM export_batches WHERE id = ?').get(info.lastInsertRowid),
    file: stored,
    csv,
  };
}

export function listExportBatches(db, user) {
  return db
    .prepare(
      `SELECT b.id, b.batch_no, b.status, b.row_count, b.note, b.created_at,
              u.full_name AS created_by_name, f.original_name, f.id AS file_id
       FROM export_batches b
       JOIN users u ON u.id = b.created_by
       LEFT JOIN stored_files f ON f.id = b.file_id
       ORDER BY b.id DESC`
    )
    .all();
}

export function updateExportBatch(db, user, batchId, { note, status }) {
  const batch = db.prepare('SELECT * FROM export_batches WHERE id = ?').get(batchId);
  if (!batch) throw notFound('Export batch not found');
  if (batch.created_by !== user.id && user.role !== 'admin') throw forbidden('You can only edit your own export batches');
  const fields = [];
  const params = [];
  if (note !== undefined) {
    fields.push('note = ?');
    params.push(note || null);
  }
  if (status !== undefined) {
    if (!['generated', 'completed', 'archived'].includes(status)) throw badRequest('Invalid batch status');
    fields.push('status = ?');
    params.push(status);
  }
  if (!fields.length) throw badRequest('Nothing to update');
  params.push(batchId);
  db.prepare(`UPDATE export_batches SET ${fields.join(', ')} WHERE id = ?`).run(...params);
  writeAudit(db, { actorId: user.id, action: 'export.updated', entityType: 'export_batch', entityId: batchId });
  return db.prepare('SELECT * FROM export_batches WHERE id = ?').get(batchId);
}
