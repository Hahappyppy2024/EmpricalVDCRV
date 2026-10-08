import fs from 'node:fs';
import path from 'node:path';
import { config } from '../config.js';
import { hashPassword } from '../lib/crypto.js';
import { writeAudit, writeActivity } from '../services/auditService.js';

function clear(db) {
  const tables = [
    'client_error_states',
    'employee_data_accesses',
    'audit_events',
    'export_batches',
    'policy_rules',
    'activity_events',
    'comments',
    'finance_reviews',
    'approvals',
    'receipts',
    'expense_lines',
    'expense_reports',
    'stored_files',
    'sessions',
    'users',
    'categories',
    'cost_centers',
    'departments',
  ];
  const deletes = tables.map((t) => db.prepare(`DELETE FROM ${t}`));
  const txn = db.transaction(() => {
    for (const del of deletes) del.run();
  });
  txn();
}

function writeSeedFile(db, { originalName, ownerId, entityType, entityId, mime, content }) {
  const storedName = `seed-${Date.now()}-${Math.random().toString(16).slice(2)}.bin`;
  const filePath = path.join(config.uploadDir, storedName);
  fs.writeFileSync(filePath, content);
  const info = db
    .prepare(
      `INSERT INTO stored_files (original_name, stored_name, mime_type, size, owner_id, entity_type, entity_id, path)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`
    )
    .run(originalName, storedName, mime, Buffer.byteLength(content), ownerId, entityType, entityId, filePath);
  return info.lastInsertRowid;
}

export function seedDatabase(db) {
  clear(db);
  const tx = db.transaction(() => {
    const now = () => new Date().toISOString().replace('T', ' ').slice(0, 19);

    const insDept = db.prepare(`INSERT INTO departments (code, name, created_at) VALUES (?, ?, ?)`);
    const deptEng = insDept.run('DEPT-ENG', 'Engineering', now()).lastInsertRowid;
    const deptSal = insDept.run('DEPT-SAL', 'Sales', now()).lastInsertRowid;
    const deptFin = insDept.run('DEPT-FIN', 'Finance', now()).lastInsertRowid;
    const deptOps = insDept.run('DEPT-OPS', 'Operations', now()).lastInsertRowid;

    const insCc = db.prepare(
      `INSERT INTO cost_centers (code, name, department_id, created_at) VALUES (?, ?, ?, ?)`
    );
    const ccEng = insCc.run('CC-ENG', 'Engineering Cost Center', deptEng, now()).lastInsertRowid;
    const ccSal = insCc.run('CC-SAL', 'Sales Cost Center', deptSal, now()).lastInsertRowid;
    const ccFin = insCc.run('CC-FIN', 'Finance Cost Center', deptFin, now()).lastInsertRowid;
    const ccOps = insCc.run('CC-OPS', 'Operations Cost Center', deptOps, now()).lastInsertRowid;

    const insCat = db.prepare(
      `INSERT INTO categories (code, name, requires_receipt, created_at) VALUES (?, ?, ?, ?)`
    );
    const catTravel = insCat.run('CAT-TRAVEL', 'Travel', 1, now()).lastInsertRowid;
    const catMeals = insCat.run('CAT-MEALS', 'Meals', 1, now()).lastInsertRowid;
    const catOffice = insCat.run('CAT-OFFICE', 'Office Supplies', 0, now()).lastInsertRowid;
    const catSoftware = insCat.run('CAT-SOFTWARE', 'Software', 1, now()).lastInsertRowid;
    const catClient = insCat.run('CAT-CLIENT', 'Client Entertainment', 1, now()).lastInsertRowid;

    const insUser = db.prepare(
      `INSERT INTO users (username, password_hash, full_name, email, role, department_id, cost_center_id, manager_id, active, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)`
    );
    const pw = (plain) => hashPassword(plain);
    const admin = insUser.run('admin', pw('admin123'), 'System Administrator', 'admin@p08.test', 'admin', deptOps, ccOps, null, now()).lastInsertRowid;
    const finance1 = insUser.run('finance1', pw('finance123'), 'Fiona Finance', 'finance1@p08.test', 'finance', deptFin, ccFin, null, now()).lastInsertRowid;
    const mgr1 = insUser.run('mgr1', pw('manager123'), 'Martin Manager', 'mgr1@p08.test', 'manager', deptEng, ccEng, null, now()).lastInsertRowid;
    const mgr2 = insUser.run('mgr2', pw('manager123'), 'Sara Saleslead', 'mgr2@p08.test', 'manager', deptSal, ccSal, null, now()).lastInsertRowid;
    const alice = insUser.run('alice', pw('employee123'), 'Alice Anderson', 'alice@p08.test', 'employee', deptEng, ccEng, mgr1, now()).lastInsertRowid;
    const bob = insUser.run('bob', pw('employee123'), 'Bob Baker', 'bob@p08.test', 'employee', deptSal, ccSal, mgr2, now()).lastInsertRowid;
    const carol = insUser.run('carol', pw('employee123'), 'Carol Chen', 'carol@p08.test', 'employee', deptEng, ccEng, mgr1, now()).lastInsertRowid;

    const insReport = db.prepare(
      `INSERT INTO expense_reports (report_no, employee_id, title, purpose, department_id, cost_center_id, status, total_amount, submitted_at, submitted_to_id, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
    );
    const insLine = db.prepare(
      `INSERT INTO expense_lines (report_id, category_id, expense_date, description, merchant, amount, receipt_id, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`
    );

    // Report 1: alice submitted, awaiting manager (mgr1)
    const r1 = insReport.run('EXP-2026-0001', alice, 'Q1 client site visit', 'Travel to client office for quarterly review', deptEng, ccEng, 'submitted', 1350.5, now(), mgr1, now(), now()).lastInsertRowid;
    insLine.run(r1, catTravel, '2026-01-08', 'Flight to client site', 'Airline', 850, null, now());
    const line12 = insLine.run(r1, catMeals, '2026-01-09', 'Client lunch', 'Bistro', 120.5, null, now()).lastInsertRowid;
    insLine.run(r1, catMeals, '2026-01-10', 'Dinner during visit', 'Restaurant', 380, null, now());

    // Report 2: alice approved, awaiting finance review
    const r2 = insReport.run('EXP-2026-0002', alice, 'Software licenses renewal', 'Annual renewal of dev tools', deptEng, ccEng, 'approved', 720, now(), mgr1, now(), now()).lastInsertRowid;
    insLine.run(r2, catSoftware, '2026-01-15', 'IDE license renewal', 'Vendor', 720, null, now());

    // Report 3: bob draft
    const r3 = insReport.run('EXP-2026-0003', bob, 'Sales trip expenses', 'Draft expenses for upcoming sales trip', deptSal, ccSal, 'draft', 0, null, null, now(), now()).lastInsertRowid;
    insLine.run(r3, catOffice, '2026-02-02', 'Printed brochures', 'Print Shop', 95, null, now());

    // Report 4: alice reimbursed
    const r4 = insReport.run('EXP-2026-0004', alice, 'Office supplies purchase', 'New office supplies for the team', deptEng, ccEng, 'reimbursed', 210, now(), mgr1, now(), now()).lastInsertRowid;
    insLine.run(r4, catOffice, '2025-12-10', 'Desk organizers and paper', 'Office Mart', 210, null, now());

    // Report 5: carol changes_requested
    const r5 = insReport.run('EXP-2026-0005', carol, 'Conference attendance', 'Annual tech conference tickets', deptEng, ccEng, 'changes_requested', 640, now(), mgr1, now(), now()).lastInsertRowid;
    insLine.run(r5, catTravel, '2026-01-20', 'Conference registration', 'Conference', 640, null, now());

    // Report 6: carol submitted (another pending for mgr1)
    const r6 = insReport.run('EXP-2026-0006', carol, 'Team offsite dinner', 'Team offsite meal', deptEng, ccEng, 'submitted', 312, now(), mgr1, now(), now()).lastInsertRowid;
    insLine.run(r6, catMeals, '2026-02-05', 'Team offsite dinner', 'Trattoria', 312, null, now());

    // Report 7: bob finance_approved (awaiting reimbursement)
    const r7 = insReport.run('EXP-2026-0007', bob, 'Client entertainment', 'Dinner with key client', deptSal, ccSal, 'finance_approved', 465, now(), mgr2, now(), now()).lastInsertRowid;
    insLine.run(r7, catClient, '2026-01-25', 'Client dinner', 'Steakhouse', 465, null, now());

    // Report 8: bob approved (finance queue, but created by mgr2)
    const r8 = insReport.run('EXP-2026-0008', bob, 'Travel advance reconciliation', 'Mileage and tolls for sales route', deptSal, ccSal, 'approved', 138, now(), mgr2, now(), now()).lastInsertRowid;
    insLine.run(r8, catTravel, '2026-02-08', 'Mileage and tolls', 'Toll Authority', 138, null, now());

    // Seed receipts (files) for report 1 and 2
    const f1 = writeSeedFile(db, { originalName: 'flight-receipt.txt', ownerId: alice, entityType: 'receipt', entityId: r1, mime: 'text/plain', content: 'FLIGHT RECEIPT\nAirline: Example Air\nAmount: 850.00\nRef: FLT-2026-0108\n' });
    const f2 = writeSeedFile(db, { originalName: 'software-invoice.txt', ownerId: alice, entityType: 'receipt', entityId: r2, mime: 'text/plain', content: 'INVOICE\nVendor: Vendor Tools\nAmount: 720.00\nRef: INV-9901\n' });

    const insReceipt = db.prepare(
      `INSERT INTO receipts (report_id, line_id, file_id, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?)`
    );
    const rcpt1 = insReceipt.run(r1, null, f1, alice, now()).lastInsertRowid;
    const rcpt2 = insReceipt.run(r2, null, f2, alice, now()).lastInsertRowid;
    db.prepare(`UPDATE expense_lines SET receipt_id = ? WHERE id = ?`).run(f1, line12);

    const insApproval = db.prepare(
      `INSERT INTO approvals (report_id, decision, comment, decided_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)`
    );
    const ap4 = insApproval.run(r4, 'approved', 'Approved - office supplies within policy', mgr1, now(), now()).lastInsertRowid;
    insApproval.run(r2, 'approved', 'Approved - software renewal is justified', mgr1, now(), now());
    insApproval.run(r5, 'changes_requested', 'Please add a receipt for the registration fee', mgr1, now(), now());
    insApproval.run(r7, 'approved', 'Approved - client entertainment within limit', mgr2, now(), now());
    insApproval.run(r8, 'approved', 'Approved - mileage documented', mgr2, now(), now());

    const insReview = db.prepare(
      `INSERT INTO finance_reviews (report_id, decision, comment, decided_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)`
    );
    insReview.run(r4, 'reimbursed', 'Reimbursed - office supplies batch', finance1, now(), now());
    insReview.run(r7, 'approved', 'Reviewed and approved for reimbursement', finance1, now(), now());

    const insPolicy = db.prepare(
      `INSERT INTO policy_rules (code, name, rule_type, category_id, amount_threshold, requires_receipt, enabled, created_by, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
    );
    insPolicy.run('POL-REPORT-MAX', 'Maximum amount per report', 'max_report_amount', null, 5000, null, 1, admin, now(), now());
    insPolicy.run('POL-RECEIPT-REQ', 'Receipt required for travel/meals', 'requires_receipt', null, null, 1, 1, admin, now(), now());
    insPolicy.run('POL-SOFTWARE-LIMIT', 'Software category limit', 'category_limit', catSoftware, 1500, null, 1, admin, now(), now());
    insPolicy.run('POL-APPROVE-THRESHOLD', 'Manager approval threshold', 'approval_threshold', null, 200, null, 1, admin, now(), now());

    const insComment = db.prepare(
      `INSERT INTO comments (report_id, author_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?)`
    );
    const c1 = insComment.run(r1, alice, 'Please prioritize this before month end.', now(), now()).lastInsertRowid;
    insComment.run(r1, mgr1, 'Thanks Alice, reviewing now.', now(), now());
    insComment.run(r2, alice, 'Invoice attached.', now(), now());

    writeActivity(db, { reportId: r1, actorId: alice, type: 'report.submitted', message: 'Report EXP-2026-0001 submitted for approval' });
    writeActivity(db, { reportId: r2, actorId: mgr1, type: 'approval.decision', message: 'Report EXP-2026-0002 approved by manager' });
    writeActivity(db, { reportId: r4, actorId: finance1, type: 'finance.reimbursed', message: 'Report EXP-2026-0004 reimbursed' });
    writeActivity(db, { reportId: r1, actorId: alice, type: 'comment.created', message: 'Alice commented on report EXP-2026-0001' });

    const insExport = db.prepare(
      `INSERT INTO export_batches (batch_no, status, file_id, created_by, row_count, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)`
    );
    const expFile = writeSeedFile(db, { originalName: 'reimbursement-export-001.csv', ownerId: finance1, entityType: 'export', entityId: null, mime: 'text/csv', content: 'report_no,employee,total,status\nEXP-2026-0004,Alice Anderson,210,reimbursed\n' });
    insExport.run('EXP-BATCH-2026-01', 'generated', expFile, finance1, 1, 'January reimbursement run', now());

    const insAccess = db.prepare(
      `INSERT INTO employee_data_accesses (viewer_id, subject_id, report_id, action, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)`
    );
    insAccess.run(mgr1, alice, r1, 'view', 'Reviewed during approval workflow', now(), now());

    writeAudit(db, { actorId: admin, action: 'seed.initialized', entityType: 'system', details: { project: 'P08' } });
  });

  tx();
  return db;
}
